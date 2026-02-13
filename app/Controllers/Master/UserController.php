<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\ProcessModel;
use App\Models\UserProcessModel;
use App\Models\MenuModel;
use App\Models\UserPrivilegeModel;

class UserController extends BaseController
{
    protected UserModel $userModel;
    protected ProcessModel $processModel;
    protected UserProcessModel $userProcessModel;
    protected MenuModel $menuModel;
    protected UserPrivilegeModel $userPrivilegeModel;

    public function __construct()
    {
        $this->userModel         = new UserModel();
        $this->processModel      = new ProcessModel();
        $this->userProcessModel  = new UserProcessModel();
        $this->menuModel         = new MenuModel();
        $this->userPrivilegeModel= new UserPrivilegeModel();
    }

    public function index()
    {
        $keyword = trim((string)$this->request->getGet('keyword'));
        $role    = trim((string)$this->request->getGet('role'));
        $perPage = (int)($this->request->getGet('perPage') ?? 10);

        $perPageOptions = [10,25,50,100];
        if (!in_array($perPage, $perPageOptions, true)) $perPage = 10;

        $roleOptions = ['ADMIN','PPIC','OPERATOR','QC','MANAGER'];

        $builder = $this->userModel->orderBy('id', 'DESC');

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('username', $keyword)
                ->orLike('fullname', $keyword)
                ->groupEnd();
        }

        if ($role !== '' && in_array($role, $roleOptions, true)) {
            $builder->where('role', $role);
        }

        $users = $builder->paginate($perPage, 'users');

        // ambil pivot process untuk semua user yang ada di page
        $ids = array_map(fn($r) => (int)$r['id'], $users ?: []);
        $mapProcessIds = [];
        $mapProcessNames = [];

        if (!empty($ids)) {
            $rows = $this->userProcessModel
                ->select('user_processes.user_id, production_processes.id as process_id, production_processes.process_name')
                ->join('production_processes', 'production_processes.id = user_processes.process_id', 'left')
                ->whereIn('user_processes.user_id', $ids)
                ->orderBy('production_processes.process_code', 'ASC')
                ->findAll();

            foreach ($rows as $r) {
                $uid = (int)$r['user_id'];
                $mapProcessIds[$uid][]   = (int)$r['process_id'];
                $mapProcessNames[$uid][] = (string)$r['process_name'];
            }
        }

        foreach ($users as &$u) {
            $uid = (int)$u['id'];
            $u['process_ids']   = $mapProcessIds[$uid] ?? [];
            $u['process_names'] = $mapProcessNames[$uid] ?? [];
        }
        unset($u);

        $processes = $this->processModel->orderBy('process_code', 'ASC')->findAll();

        return view('master/user/index', [
            'users'           => $users,
            'pager'           => $this->userModel->pager,
            'keyword'         => $keyword,
            'role'            => $role,
            'perPage'         => $perPage,
            'perPageOptions'  => $perPageOptions,
            'roleOptions'     => $roleOptions,
            'processes'       => $processes,
        ]);
    }

    public function store()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'password' => 'required|min_length[6]',
            'role'     => 'required|in_list[ADMIN,PPIC,OPERATOR,QC,MANAGER]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $role = (string)$this->request->getPost('role');
        $sections = (array)($this->request->getPost('sections') ?? []); // checkbox -> array process_id

        if ($role !== 'ADMIN' && empty($sections)) {
            return redirect()->back()->withInput()->with('error', 'Role selain ADMIN wajib pilih minimal 1 section/process.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $userId = $this->userModel->insert([
                'username' => trim((string)$this->request->getPost('username')),
                'fullname' => trim((string)$this->request->getPost('fullname')),
                'password' => password_hash((string)$this->request->getPost('password'), PASSWORD_DEFAULT),
                'role'     => $role,
            ], true);

            // sync pivot process
            if ($role !== 'ADMIN') {
                $insert = [];
                foreach ($sections as $pid) {
                    $pid = (int)$pid;
                    if ($pid > 0) $insert[] = ['user_id' => (int)$userId, 'process_id' => $pid];
                }
                if (!empty($insert)) $this->userProcessModel->insertBatch($insert);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/user')->with('success', 'User berhasil ditambahkan');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update($id)
    {
        $id = (int)$id;
        $user = $this->userModel->find($id);
        if (!$user) return redirect()->to('/master/user')->with('error', 'User tidak ditemukan');

        $rules = [
            'username' => "required|min_length[3]|max_length[50]|is_unique[users.username,id,{$id}]",
            'role'     => 'required|in_list[ADMIN,PPIC,OPERATOR,QC,MANAGER]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $role = (string)$this->request->getPost('role');
        $sections = (array)($this->request->getPost('sections') ?? []);

        if ($role !== 'ADMIN' && empty($sections)) {
            return redirect()->back()->withInput()->with('error', 'Role selain ADMIN wajib pilih minimal 1 section/process.');
        }

        $data = [
            'username' => trim((string)$this->request->getPost('username')),
            'fullname' => trim((string)$this->request->getPost('fullname')),
            'role'     => $role,
        ];

        $newPass = trim((string)$this->request->getPost('password'));
        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                return redirect()->back()->withInput()->with('error', 'Password minimal 6 karakter');
            }
            $data['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->userModel->update($id, $data);

            // sync pivot: reset lalu insert ulang
            $this->userProcessModel->where('user_id', $id)->delete();

            if ($role !== 'ADMIN') {
                $insert = [];
                foreach ($sections as $pid) {
                    $pid = (int)$pid;
                    if ($pid > 0) $insert[] = ['user_id' => $id, 'process_id' => $pid];
                }
                if (!empty($insert)) $this->userProcessModel->insertBatch($insert);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/user')->with('success', 'User berhasil diupdate');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete($id)
    {
        $id = (int)$id;
        $user = $this->userModel->find($id);
        if (!$user) return redirect()->to('/master/user')->with('error', 'User tidak ditemukan');

        $this->userModel->delete($id);
        return redirect()->to('/master/user')->with('success', 'User berhasil dihapus');
    }

    /**
     * Load modal detail privilege (HTML partial)
     */
    public function privilege($id)
    {
        $id = (int)$id;
        $user = $this->userModel->find($id);
        if (!$user) return $this->response->setStatusCode(404)->setBody('User tidak ditemukan');

        // Ambil semua menu (harus include parent_id)
        $menus = $this->menuModel
            ->select('id,parent_id,name,route,icon,sort_order')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $menuRows = $this->buildMenuTreeWithNumbering($menus);

        $privRows = $this->userPrivilegeModel->where('user_id', $id)->findAll();
        $privMap = [];
        foreach ($privRows as $p) {
            $privMap[(int)$p['menu_id']] = $p;
        }

        return view('master/user/_privilege_modal_body', [
            'user'     => $user,
            'menuRows' => $menuRows,
            'privMap'  => $privMap,
        ]);
    }


    /**
     * Save privilege per user
     */
    public function savePrivilege($id)
    {
        $id = (int)$id;
        $user = $this->userModel->find($id);
        if (!$user) return redirect()->to('/master/user')->with('error', 'User tidak ditemukan');

        // ADMIN => (optional) kamu bisa skip penyimpanan privilege karena all access
        if (($user['role'] ?? '') === 'ADMIN') {
            return redirect()->to('/master/user')->with('success', 'ADMIN tidak perlu diatur privilege (All Access)');
        }

        $menuIds = (array)($this->request->getPost('menu_id') ?? []);
        $reads   = (array)($this->request->getPost('can_read') ?? []);
        $creates = (array)($this->request->getPost('can_create') ?? []);
        $updates = (array)($this->request->getPost('can_update') ?? []);
        $deletes = (array)($this->request->getPost('can_delete') ?? []);
        $access  = (array)($this->request->getPost('data_access') ?? []);

        $db = db_connect();
        $db->transBegin();

        try {
            foreach ($menuIds as $mid) {
                $mid = (int)$mid;
                if ($mid <= 0) continue;

                $row = [
                    'user_id'    => $id,
                    'menu_id'    => $mid,
                    'can_read'   => isset($reads[$mid]) ? 1 : 0,
                    'can_create' => isset($creates[$mid]) ? 1 : 0,
                    'can_update' => isset($updates[$mid]) ? 1 : 0,
                    'can_delete' => isset($deletes[$mid]) ? 1 : 0,
                    'data_access'=> in_array(($access[$mid] ?? 'ALL'), ['ALL','SECTION','OWN'], true) ? $access[$mid] : 'ALL',
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $exists = $this->userPrivilegeModel
                    ->where('user_id', $id)
                    ->where('menu_id', $mid)
                    ->first();

                if ($exists) {
                    $this->userPrivilegeModel->update($exists['id'], $row);
                } else {
                    $this->userPrivilegeModel->insert($row);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/user')->with('success', 'Privilege berhasil disimpan');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->to('/master/user')->with('error', $e->getMessage());
        }
    }

    private function buildMenuTreeWithNumbering(array $menus): array
    {
        // group by parent_id
        $byParent = [];
        foreach ($menus as $m) {
            $pid = $m['parent_id'] ?? null;
            $key = $pid === null ? 'root' : (string)$pid;
            $byParent[$key][] = $m;
        }

        // pastikan urut sesuai sort_order lalu id
        foreach ($byParent as &$list) {
            usort($list, function($a, $b){
                $sa = (int)($a['sort_order'] ?? 0);
                $sb = (int)($b['sort_order'] ?? 0);
                if ($sa === $sb) return ((int)$a['id']) <=> ((int)$b['id']);
                return $sa <=> $sb;
            });
        }
        unset($list);

        $rows = [];

        // recursive
        $walk = function($parentKey, $prefix) use (&$walk, &$rows, $byParent) {
            $children = $byParent[$parentKey] ?? [];
            $i = 0;
            foreach ($children as $child) {
                $i++;
                $number = $prefix === '' ? (string)$i : ($prefix . '.' . $i);

                $rows[] = [
                    'number' => $number,
                    'level'  => substr_count($number, '.'), // 0=root, 1=child, 2=grandchild...
                    'menu'   => $child,
                ];

                $childKey = (string)$child['id'];
                $walk($childKey, $number);
            }
        };

        $walk('root', '');

        return $rows; // flattened tree rows with numbering
    }

}
