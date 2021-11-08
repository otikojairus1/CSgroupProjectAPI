<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use Illuminate\Http\Request;

class RegisterUsersController extends Controller
{
    //endpoint for registering new users
    public function store(Request $request)
    {
       // dd($request);
       $rules = [
        'name' => 'unique:users|required',
        'phone' => 'unique:users|required',
        'email'    => 'unique:users|required',
        'password' => 'required',
    ];

    $input     = $request->only('name', 'phone','email','password');
    $validator = Validator::make($input, $rules);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'error' => $validator->messages()]);
    }
    $name = $request->name;
    $email    = $request->email;
    $password = $request->password;
    $phone = $request->phone;
    $user     = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password),'phone'=>$phone]);
    //$token = $request->name->createToken('accessToken');
    return response()->json(['success' => true, 'message' => 'user has registered successfully.']);
    }

    // endpoint for viewing the complete list of all users
    public function index(){
        $all = User::all();
        
        return response()->json(['success' => true, 'response' => $all]);

    }
// endpoint for viewing single user details
    
    public function detailinfo(Request $request ){
        $singleUser = User::find($request->id);
        if(!$singleUser){
            return response()->json(['success' => false, 'response' => 'no user matching the id']);
        }
        
        return response()->json(['success' => true, 'response' => $singleUser]);

    }





// endpoint for authenticating users
    public function login(){ 
        if(Auth::attempt(['email' => request('email'), 'password' => request('password')])){ 
           $user = Auth::user(); 
           // $success['token'] =  $user->createToken('MyApp')-> accessToken; 
           $token = $user->createToken('accessToken', ['admin']);

                if($user->email == "admin@msaadaapp.com"){
                    return response()->json(['success' => true, 'userType'=>'admin' ,'userdetails'=>$user,'token'=>$token],200); 
                }else{
                    return response()->json(['success' => true, 'userType'=>'other', 'userdetails'=>$user,'token'=>$token],200); 
                }
            
        } 
        else{ 
            return response()->json(['success'=>false,'error'=>'wrong login credentials' ], 401); 
        } 
    }

        //endpoint for editing excisting users
        public function update(Request $request, $id)
        {
           // dd($request);
           $User = User::find($id);

           if(!$User){
            return response()->json(['success' => false, 'response' => 'no user matching the id']);
        }

           $rules = [
            'name' => 'required',
            'email'    => 'email|required',
            'password' => 'required',
        ];
    
        $input     = $request->only('name', 'email','password');
        $validator = Validator::make($input, $rules);
    
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()]);
        }
        $User->name = $request->name;
        $User->email    = $request->email;
        $User->password =Hash::make($request->password);
        $User->save();
        //$response    = User::update(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        return response()->json(['success' => true, 'response' => $User]);
        }
    
        public function test(){
            return response()->json(['success' => true, 'response' => 'Welcome to the GRAD APIs ','Version' => '0.0.1','author' => 'OTIKOINC LTD',]);
        }




}