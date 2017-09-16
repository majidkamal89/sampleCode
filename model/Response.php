<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SparkPost\SparkPost;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use App\EmailTemplate;

use Mailgun\Mailgun;

class Response extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'responses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'response',
        'module_type',
        'status'
    ];

    public function modules(){
        return $this->belongsToMany('App\Module', 'module_responses', 'response_id','module_id')->withTimestamps();
    }

    public function user(){
        return $this->hasMany('App\User', 'id', 'user_id');
    }

    public function getAllResponses($moduleId){
        return $this
                ->whereHas('modules', function($query)use ($moduleId){
                    $query->where('module_id', $moduleId);
                })
                ->with('modules', 'user')
                ->get();
    }

    public function getAllResponsesWithPagination($moduleId){
        return $this
            ->whereHas('modules', function($query)use ($moduleId){
                $query->where('module_id', $moduleId);
            })
            ->with('modules', 'user')
            ->paginate(10);
    }

    public function getResponseWithDetails($responseId){
        return $this
                ->with('modules', 'user')
                ->where('id', '=', $responseId)
                ->first();
    }

    public function submitForm($request){
        try{
            if(Auth::user()){
                if(!empty($request->module_type)){
                    if(!empty($request->id)){
                        $data = [
                            'response' => json_encode($request->except('_token')),
                            'module_type' => $request->module_type,
                            'status' => 1
                        ];
                    } else {
                        $data = [
                            'user_id' => Auth::user()->id,
                            'response' => json_encode($request->except('_token')),
                            'module_type' => $request->module_type,
                            'status' => 1
                        ];
                    }
                } else {
                    if(!empty($request->module_name) && $request->module_name == 'Contact Us'){
                        $data = [
                            'user_id' => 0,
                            'response' => json_encode($request->except('_token'))
                        ];
                    } elseif(!empty($request->action) && $request->action == 'event_module'){
                        $data = [
                            'user_id' => 0,
                            'response' => json_encode($request->except('_token'))
                        ];
                    } else {
                        $data = [
                            'user_id' => Auth::user()->id,
                            'response' => json_encode($request->except('_token'))
                        ];
                    }
                }
            } else {
                if(!empty($request->user_id)){
                    $data = [
                        'user_id' => $request->user_id,
                        'response' => json_encode($request->except(['_token','profilepic'])),
                        'module_type' => $request->module_type
                    ];
                } else {
                    $data = [
                        'user_id' => 0,
                        'response' => json_encode($request->except(['_token','profilepic']))
                    ];
                }
            }
            if(!empty($request->id)){
                $result = $this->find($request->id)->update($data);
                $responseID = $request->id;
            } else {
                $result = $this->create($data);
                $module = $result->modules()->attach($request->module_id);
                $responseID = $result->id;
            }
            $this->template = new EmailTemplate();
            $user_emails = DB::table('users')
                ->select(['users.email','users.first_name','users.last_name'])
                ->join('notifications', 'users.id', '=', 'notifications.user_id')
                ->where('notifications.module_id', $request->module_id)->get();
            if(count($user_emails) > 0){
                $template = EmailTemplate::where('template_type', 3)->first();
                $subject = 'Update in module';
                $message = 'Please click this link <a href="'.url('/admin/response', [$responseID]).'">View</a> to see changes in module.';
                $link = '<a href="'.url('/admin/response', [$responseID]).'">View</a>';
                $module_type = Module::where('id', $request->module_id)->first(['file_name']);
                foreach($user_emails as $val){
                    if($module_type->file_name == 'event_module_page'){
                        $submission_detail = 'First Name:'.$request->first_name.'<br/>Last Name:'.$request->last_name.'<br/>Phone:'.$request->phone.'<br/>NPN:'.$request->npn.'<br/>
                        Date:'.(!empty($request->date) ? $request->date:'').'<br/>Time:'.(!empty($request->time) ? $request->time:'').'<br/>Attendees:'.$request->attendees.'<br/>
                        State:'.$request->state.'<br/>Email:'.$request->email.'<br/>Office Address:'.$request->office_address.'';
                        $content_array = [
                            'admin_name' => $val->first_name.' '.$val->last_name,
                            'submission_detail' => $submission_detail,
                            'link' => $link,
                        ];
                    } elseif($module_type->file_name == 'PRI'){
                        $submission_detail = 'First Name:'.$request->first_name.'<br/>Middle Name:'.$request->middle_name.'<br/>Last Name:'.$request->last_name.'<br/>Address:'.$request->address.'<br/>
                        Apt Number:'.$request->apt_number.'<br/>City:'.$request->city.'<br/>Zip:'.$request->zip_code.'<br/>
                        Phone:'.$request->phone.'<br/>Date of Birth:'.$request->dob.'<br/>Medicare Number:'.$request->medicare_number.'<br/>Medicare Plan:'.$request->medicare_plan_name.'<br/>
                        Carrier:'.$request->carrier.'<br/>Physician Name:'.$request->physician_name.'<br/>Comfort Health Office:'.$request->comfort_health_office.'<br/>
                        Reason:'.$request->reason.'<br/>Reason Detail:'.$request->reason_detail.'<br/>Company Name:'.$request->company_name.'<br/>Signed by:'.$request->signed.'<br/>';
                        $content_array = [
                            'admin_name' => $val->first_name.' '.$val->last_name,
                            'submission_detail' => $submission_detail,
                            'link' => $link,
                        ];
                    } elseif($module_type->file_name == 'contact_us'){
                        $submission_detail = 'Name:'.$request->name.'<br/>Email:'.$request->email.'<br/>Phone:'.$request->phone.'<br/>Subject:'.$request->subject.'<br/>
                        State:'.$request->state.'<br/>Message:'.$request->message.'<br/>';
                        $content_array = [
                            'admin_name' => $val->first_name.' '.$val->last_name,
                            'submission_detail' => $submission_detail,
                            'link' => $link,
                        ];
                    } else {
                        $content_array = [
                            'admin_name' => $val->first_name.' '.$val->last_name,
                            'submission_detail' => '',
                            'link' => $link,
                        ];
                    }
                    if(count($template) > 0){
                        $message = $this->template->build($content_array,3);
                        $subject = $template->subject;
                    }
                    $this->sendEmail($val->email,$subject,$message);
                }
            }
            ///// Code for sending email to manual entered emails /////
            $custom_email_list = DB::table('notifications')
                ->select(DB::raw('GROUP_CONCAT(custom_email) as emails'))
                ->where('module_id', $request->module_id)
                ->where('custom_email', '!=', '')->first();
            if(!empty($custom_email_list->emails)){
                $email_list = explode(',', $custom_email_list->emails);
                $template = EmailTemplate::where('template_type', 3)->first();
                $module_type = Module::where('id', $request->module_id)->first(['file_name']);
                $subject = 'Update in module';
                $message = 'Please click this link <a href="'.url('/admin/response', [$responseID]).'">View</a> to see changes in module.';
                $link = '<a href="'.url('/admin/response', [$responseID]).'">View</a>';
                if($module_type->file_name == 'event_module_page'){
                    $submission_detail = 'First Name:'.$request->first_name.'<br/>Last Name:'.$request->last_name.'<br/>Phone:'.$request->phone.'<br/>NPN:'.$request->npn.'<br/>
                        Date:'.(!empty($request->date) ? $request->date:'').'<br/>Time:'.(!empty($request->time) ? $request->time:'').'<br/>Attendees:'.$request->attendees.'<br/>
                        State:'.$request->state.'<br/>Email:'.$request->email.'<br/>Office Address:'.$request->office_address.'';
                    $content_array = [
                        'admin_name' => '',
                        'submission_detail' => $submission_detail,
                        'link' => $link,
                    ];
                } elseif($module_type->file_name == 'PRI'){
                    $submission_detail = 'First Name:'.$request->first_name.'<br/>Middle Name:'.$request->middle_name.'<br/>Last Name:'.$request->last_name.'<br/>Address:'.$request->address.'<br/>
                        Apt Number:'.$request->apt_number.'<br/>City:'.$request->city.'<br/>Zip:'.$request->zip_code.'<br/>
                        Phone:'.$request->phone.'<br/>Date of Birth:'.$request->dob.'<br/>Medicare Number:'.$request->medicare_number.'<br/>Medicare Plan:'.$request->medicare_plan_name.'<br/>
                        Carrier:'.$request->carrier.'<br/>Physician Name:'.$request->physician_name.'<br/>Comfort Health Office:'.$request->comfort_health_office.'<br/>
                        Reason:'.$request->reason.'<br/>Reason Detail:'.$request->reason_detail.'<br/>Company Name:'.$request->company_name.'<br/>Signed by:'.$request->signed.'<br/>';
                    $content_array = [
                        'admin_name' => '',
                        'submission_detail' => $submission_detail,
                        'link' => $link,
                    ];
                } elseif($module_type->file_name == 'contact_us'){
                    $submission_detail = 'Name:'.$request->name.'<br/>Email:'.$request->email.'<br/>Phone:'.$request->phone.'<br/>Subject:'.$request->subject.'<br/>
                        State:'.$request->state.'<br/>Message:'.$request->message.'<br/>';
                    $content_array = [
                        'admin_name' => '',
                        'submission_detail' => $submission_detail,
                        'link' => $link,
                    ];
                } else {
                    $content_array = [
                        'admin_name' => '',
                        'submission_detail' => '',
                        'link' => $link,
                    ];
                }
                if(count($template) > 0){
                    $message = $this->template->build($content_array,3);
                    $subject = $template->subject;
                }
                $this->sendEmail($email_list,$subject,$message);
            }
            /// End Email Code ///

            if(!empty($request->user_id) && !empty($request->subdomain)){
                $template = EmailTemplate::where('template_type', 4)->first();
                $link = '<a href="'.url('/mywebpage/'.$request->subdomain.'').'">Link</a>';
                if(count($template) > 0){
                    $message = $this->template->build([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'link' => $link,
                        'username' => $request->email,
                        'password' => $request->subdomain,
                    ],4);
                    $subject = $template->subject;
                } else {
                    $message = 'Dear '.$request->first_name.' '.$request->last_name.', <br/>Your sub domain and account created successfully on Carefree. Here is link of your website <a href="'.url('/mywebpage/'.$request->subdomain.'').'">Link</a>.
                    <br/> Your username '.$request->email.' and password is '.$request->subdomain.'.';
                    $subject = 'Domain Created';
                }
                $this->sendEmail($request->email,$subject,$message);
            } elseif(Auth::user() && !empty($request->subdomain)){
                $template = EmailTemplate::where('template_type', 2)->first();
                $link = '<a href="'.url('/mywebpage/'.$request->subdomain.'').'">Link</a>';
                if(count($template) > 0){
                    $message = $this->template->build([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'link' => $link
                    ],2);
                    $subject = $template->subject;
                } else {
                    $message = 'Dear '.$request->first_name.' '.$request->last_name.', <br/>Your sub domain created successfully on Carefree. Here is link of your website <a href="'.url('/mywebpage/'.$request->subdomain.'').'">Link</a>.';
                    $subject = 'Domain Created';
                }
                $this->sendEmail($request->email,$subject,$message);
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

    public function getUserResponses($userId){
        return $this
            ->with('modules', 'user')
            ->where('user_id', '=', $userId)
            ->get();
    }

    public function sendEmail($email,$subject,$message){

        $mg = new Mailgun("KEY-here");
        $domain = "test.com";
        $mg->sendMessage($domain, array(
            'from'    => '#####',
            'to'      => $email,
            'subject' => $subject,
            'html'    => $message));
        return true;

        /*$httpClient = new GuzzleAdapter(new Client());
        $sparky = new SparkPost($httpClient, ['key' => env('SPARKPOST_KEY')]);

        $sparky->setOptions(['async' => false]);
        $sparky->setOptions(['sandbox' => true]);
        $results = $sparky->transmissions->post([
            'content' => [
                'from' => '#######',
                'subject' => $subject,
                'html' => $message
            ],
            'recipients' => [
                $email
            ]
        ]);
        return $results->getBody();*/
    }
}
