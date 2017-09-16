<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;

class UserModuleRole extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'module_id',
        'role_id',
    ];


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_module_roles';

    public function assignRole($request){
        try{

            if(strpos($request->user, ',') != true ) {
                $data = [
                    'user_id' => $request->user,
                    'module_id' => $request->module,
                    'role_id' => $request->role
                ];
                $result = $this->create($data);
                if(!$request->ajax()){
                    return redirect('/admin/dashboard');
                }

            } else {
                $users = explode(',', $request->user);
                foreach ($users as $key=>$value) {
                    $data = [
                        'user_id' => $value,
                        'module_id' => $request->module,
                        'role_id' => $request->role
                    ];
                    $result = $this->create($data);
                }

                return redirect('/admin/dashboard');
            }

            return collect([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e){
            return collect([
                'status' => 'failure',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updateRole($request){
        try{
            $data = [
                'module_id' => $request->module,
                'role_id' => $request->role
            ];
            $prevRecord = $this
                ->where('user_id', '=', $request->userId)
                ->where('module_id', '=', $request->moduleId)->first();
            if(count($prevRecord) > 0){
                $result = $this->where('user_id', '=', $request->userId)->where('module_id', '=', $request->moduleId)->update($data);
                $delete_notification = Notification::where('user_id', '=', $request->userId)->where('module_id', '=', $request->moduleId)->forceDelete();
            } else {
                $result = $this->create([
                    'module_id' => $request->module,
                    'role_id' => $request->role,
                    'user_id' => $request->userId
                ]);
            }
            return collect([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e){
            return collect([
                'status' => 'failure',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function roles(){
        return $this->hasMany('App\Role', 'id', 'role_id');
    }

    public function modules(){
        return $this->hasMany('App\Module', 'id', 'module_id');
    }

    public function users(){
        return $this->hasMany('App\User', 'id', 'user_id');
    }

    public function getUserRole($moduleId){
        $userModuleRole = $this
                            ->with('roles')
                            ->where('module_id', '=', $moduleId)
                            ->first();

        return $userModuleRole->roles[0]->role_name;
    }

    public function getUserModuleRole($moduleId, $userId){
        $userModuleRole = $this
                            ->with('roles')
                            ->where('module_id', '=', $moduleId)
                            ->where('user_id', '=', $userId)
                            ->first();
//        dump($moduleId, $userId);
        return $userModuleRole->roles[0]->role_name;
    }

    public function getUserModules($roleId = 2){
        $userModules = $this
                        ->with('modules')
                        ->where('role_id', '=', $roleId)
                        ->where('user_id', '=', Auth::user()->id)
                        ->get();
        return $userModules;
    }

    public function getAllUserModules(){
        return $this
                ->with('modules')
                ->where('user_id', '=', Auth::user()->id)
                ->get();
    }
}
