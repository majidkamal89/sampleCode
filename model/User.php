<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use App\EmailTemplate;
use App\Response;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'npn',
        'email',
        'state',
        'carriers',
        'gender',
        'source',
        'address',
        'city',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    public function carriers(){
        return $this->belongsToMany('App\Carrier', 'user_carriers', 'user_id','carrier_id')->withTimestamps();
    }

    public function roles(){
        return $this->hasMany('App\UserModuleRole', 'user_id', 'id');
    }

    public function modules(){
        return $this->hasMany('App\UserModuleRole', 'user_id', 'id');
    }

    public function register($request){
        try {
            $carriers = (!empty($request['carriers']) ? implode(',', $request['carriers']):'');
            $array = [
                'first_name' => $request['first_name'],
                'last_name' => $request['last_name'],
                'phone' => $request['phone'],
                'npn' => $request['npn'],
                'email' => $request['email'],
                'state' => $request['state'],
                'password' => bcrypt('Password123!'),
                'is_admin' => false,
                'carriers' => $carriers,
                'password_reset' => 0
            ];
            $user = $this->create($array);
            $email_template = EmailTemplate::where('template_type', 5)->first();
            $this->response = new Response();
            if(count($email_template) > 0){
                $this->template = new EmailTemplate();
                $message = $this->template->build($array,5);
                $subject = $email_template->subject;
                $this->response->sendEmail($request['email'],$subject,$message);
            } else {
                $this->response->sendEmail($request['email'],'Carefree Agency','Thank you for creating your account on carefreeagency.com.');
            }
            $module = Module::where('file_name', 'web_page')->first(['id']);
            if(count($module) > 0){
                $role = Role::where('role_name', 'user')->first(['id']);
                $user_module_role = new UserModuleRole();
                $user_module_role->create(['user_id' => $user->id, 'module_id' => $module->id, 'role_id' => $role->id, 'created_at' => date('Y-m-d H:is'), 'updated_at' => date('Y-m-d H:is')]);
            }

            /*foreach($request['carriers'] as $key => $value){
                $user->carriers()->attach($value);
            }*/
            $login = Auth::attempt(['email' => $request['email'], 'password' => 'Password123!']);
            return collect([
                'status' => 'success',
                'data' => $user
            ]);
        } catch(\Exception $e){
            return collect([
                'status' => 'failure',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getUserWithAllDetails($id){
        return $this
                ->with('carriers', 'roles.roles', 'modules.modules')
                ->where('id', '=', $id)
                ->first();
    }

    public function getAllUsersWithDetails(){
        return $this
                ->where('is_admin', '!=', '1')
                ->get();
    }

    public function getAssignedUsersWithDetails(){
        return $this
                ->whereHas('roles', function ($query){})
                ->with('carriers', 'roles.roles', 'modules.modules')
                ->where('is_admin', '!=', '1')
                ->paginate(20);
    }

    public function getNewUsers(){
        return $this
                ->whereDoesntHave('roles', function ($query){})
                ->with('roles')
                ->where('is_admin' ,'<', '1')
                ->get();
    }

    public function hasRole($roleName, $userId = false){
        if($userId == false){
            $userId = Auth::user()->id;
        }
        if($roleName == 'admin'){
            $roleId = 1;
        } else {
            $roleId = 2;
        }
        $userRole = $this
                    ->whereHas('roles', function($query)use ($roleId){
                        $query->where('role_id', $roleId);
                    })
                    ->with('roles')
                    ->where('id', '=', $userId)
                    ->get();

        $return = false;
        if(count($userRole) > 0){
            foreach ($userRole[0]->roles as $role){
                if($role->role_id == 1){
                    $return = true;
                }
            }
        }
        return $return;
    }

    public function getUserModules(){
        return $this->with('modules.modules')->where('id', '=', Auth::user()->id)->get();
    }

    public function getUserModulesWithRoleUser($roleId){
        $obj = new UserModuleRole();
        return $obj->getAllUserModules();
        //return $obj->getUserModules($roleId);
    }

    public function getUserModulesWithRoleAdmin($roleId){
        $obj = new UserModuleRole();
        return $obj->getUserModules($roleId);
    }

    public function getModuleUsers($moduleId){
        return $this
            ->whereHas('modules', function ($query)use($moduleId){
                $query->where('module_id', '=', $moduleId);
            })
            ->with('carriers', 'roles.roles', 'modules.modules')
            ->where('is_admin', '!=', '1')
            ->get();
    }

    public function getUserThirdPartyLink(){
        $result = DB::table('users')
            ->select('thirdparty_link.*')
            ->join('thirdparty_users', 'users.id', '=', 'thirdparty_users.user_id')
            ->join('thirdparty_link', 'thirdparty_users.thirdparty_id', '=', 'thirdparty_link.id')
            ->where('users.id', Auth::user()->id)
            ->get();
        return $result;
    }

}
