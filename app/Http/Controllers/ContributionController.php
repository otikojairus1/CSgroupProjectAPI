<?php

namespace App\Http\Controllers;

use App\Models\callback;
use App\Models\CompletedTransaction;
use App\Models\Contribution;
use App\Models\PendingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContributionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $all = Contribution::all();
        return response()->json(['success' => true, 'message' => 'fundraiser list generated successfully.' ,"list" => $all]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function pay( Request $request)
    {
       // get the Oauth Bearer access Token from safaricom
       //dd(int($request->amount));

       $rules =
           ['amount'=> 'required|integer',
           'phone'=>'required|integer',
           'id'=>'required|integer'
           ]
       ;

       $input     = $request->only('amount','phone', 'id');
       $validator = Validator::make($input, $rules);

       if ($validator->fails()) {
           return response()->json(['success' => false, 'error' => $validator->messages()]);
       }

       $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
      // $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

       $curl = curl_init();
       curl_setopt($curl, CURLOPT_URL, $url);
       $credentials = base64_encode('D5VGIIfdrsmTHv7dCwGyo4hubU2YFFxN:XelQksS4JcMXfVMI');
       curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials)); //setting a custom header
       curl_setopt($curl, CURLOPT_HEADER, false);
       curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

       $curl_response = curl_exec($curl);

       $responce = json_decode($curl_response)->access_token;
       //dd($responce["access_token"]);
       //dd($responce->access_token);
       $accessToken = $responce; // access token here


       //mpesa user credentials
       $mpesaOnlineShortcode = "174379";
       $BusinessShortCode = $mpesaOnlineShortcode;
       $partyA = $request->phone;
       $partyB = $BusinessShortCode;
       $phoneNumber = $partyA;
       $mpesaOnlinePasskey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
       date_default_timezone_set('Africa/Nairobi');
       $timestamp =  date('YmdHis');
       $amount = $request->amount;
       $contribution = $request->id;
       $dataToEncode = $BusinessShortCode.$mpesaOnlinePasskey.$timestamp;
       //dd($dataToEncode);
       $password = base64_encode($dataToEncode);
       //dd($password);

       //payment request to safaricom

       $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
       //$url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

       $curl = curl_init();
       curl_setopt($curl, CURLOPT_URL, $url);
       curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$accessToken)); //setting custom header


       $curl_post_data = array(
           'BusinessShortCode' => $BusinessShortCode,
           'Password' => $password,
           'Timestamp' => $timestamp,
           'TransactionType' => 'CustomerPayBillOnline',
           'Amount' =>$amount,
           'PartyA' => $partyA,
           'PartyB' => $partyB,
           'PhoneNumber' => $partyA,
           'AccountReference'=>$contribution,
           'CallBackURL' => 'https://msaadaproject.herokuapp.com/api/v2/74aqaGu3sd4/callback',
           'TransactionDesc' => 'DONATING'
       );

       $data_string = json_encode($curl_post_data);

       curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($curl, CURLOPT_POST, true);
       curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

       $curl_response = curl_exec($curl);
   // print_r($curl_response);

       ///dd($curl_response);
        $data = json_decode($curl_response);


        //updating pending transaction table
        $pending = new PendingTransaction();
       $pending->CheckoutRequestID = $data->CheckoutRequestID;
        $pending->contributionId = $contribution;
        $pending->save();

            return response()->json(['responceStatusCode'=>'200' ,'data'=>[
           'message'=>"Request accepted from safaricom, pin prompt sent successfully",
           'expectedAction' => 'query the callback api endpoint to update the UI',
           'callbackinfo'=>$curl_response, // ->CheckoutRequestID
       ]]);


}


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */


    public function callback(Request $request)
    {
        $MerchantRequestID = $request->Body['stkCallback']['MerchantRequestID'];
        $CheckoutRequestID = $request->Body['stkCallback']['CheckoutRequestID'];
        $ResultCode = $request->Body['stkCallback']['ResultCode'];
        $ResultDesc = $request->Body['stkCallback']['ResultDesc'];
        $Amount = $request->Body['stkCallback']['CallbackMetadata']['Item']['0']['Value'];
        $MpesaReceiptNumber = $request->Body['stkCallback']['CallbackMetadata']['Item']['1']['Value'];
        $TransactionDate = $request->Body['stkCallback']['CallbackMetadata']['Item']['2']['Value'];
        $PhoneNumber = $request->Body['stkCallback']['CallbackMetadata']['Item']['3']['Value'];
        //dd($PhoneNumber);

        $newTransaction = new Callback();
        $newTransaction->MerchantRequestID = $MerchantRequestID;
        $newTransaction->CheckoutRequestID = $CheckoutRequestID;
        $newTransaction->ResultCode = $ResultCode;
        $newTransaction->ResultDesc = $ResultDesc;
        $newTransaction->Amount = $Amount;
        $newTransaction->MpesaReceiptNumber = $MpesaReceiptNumber;
        $newTransaction->TransactionDate = $TransactionDate;
        $newTransaction->PhoneNumber = $PhoneNumber;
        $newTransaction->save();
    //check the pending
        $pending = PendingTransaction::where("CheckoutRequestID", $CheckoutRequestID)->first();
        if($pending != null){
            $T = new CompletedTransaction();
            $T->PhoneNumber = $PhoneNumber;
            $T->Amount = $Amount;
            $T->contributionId = $pending->contributionId;
            $T->save();
           // $pending->delete();
        }


        return response()->json(['statusCode'=>"200", 'Data'=>"transactionReceived"]);
    }

    public function contribute(Request $request)
    {
        // dd($request);
       $rules = [
        'title' => 'required',
        'description'    => 'required',
        'targetAmount' => 'required',
        'verified'=>'',
        'paymentoption' => 'required',
    ];

    $input     = $request->only('title', 'description','targetAmount','paymentoption');
    $validator = Validator::make($input, $rules);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'error' => $validator->messages()]);
    }
    $title = $request->title;
    $paymentoption = $request->paymentoption;
    $description    = $request->description;
    $targetAmount = $request->targetAmount;
    $verified = false;
    $contribution     = Contribution::create(['title' => $title, 'description' => $description, 'targetAmount' => $targetAmount, 'paymentoption' => $paymentoption ,"verified"=>$verified]);
    //$token = $request->name->createToken('accessToken');
    return response()->json(['success' => true, 'message' => 'fundraiser created successfully.']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {

             // dd($request);
       $rules = [
        'id' => 'required',

    ];

    $input     = $request->only('id');
    $validator = Validator::make($input, $rules);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'error' => $validator->messages()]);
    }

        $contribution = Contribution::find($request->id);
        if(!$contribution){
            return response()->json(['success' => false, 'response' => 'no contribution matching the id']);
        }

        return response()->json(['success' => true, 'response' => $contribution]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function verifiedContributions(Request $request)
    {

        $Contribution = Contribution::where('verified', true)->get();
        return response()->json(['success' => false, 'response' => $Contribution]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
          // dd($request);
          $Contribution = Contribution::find($request->id);

          if(!$Contribution){
           return response()->json(['success' => false, 'response' => 'no contribution matching the id']);
       }

       $rules = [
        'title' => 'required',
        'description'    => 'required',
        'targetAmount' => 'required',
        'verified'=>'required',
        'paymentoption' => 'required',
        'id'=>'required'
    ];

       $input     = $request->only('title', 'description','targetAmount','verified','paymentoption','id');
       $validator = Validator::make($input, $rules);

       if ($validator->fails()) {
           return response()->json(['success' => false, 'error' => $validator->messages()]);
       }
       $title = $request->title;
       $paymentoption = $request->paymentoption;
       $description    = $request->description;
       $targetAmount = $request->targetAmount;
       $verified = $request->verified;
       $id = $request->id;
       //$contribution     = Contribution::update(['title' => $title, 'description' => $description, 'targetAmount' => $targetAmount, 'paymentoption' => $paymentoption ,"verified"=>$verified]);
       //$token = $request->name->createToken('accessToken');
       $Contribution->title = $title;
       $Contribution->paymentoption = $paymentoption;
       $Contribution->description = $description;
       $Contribution->targetAmount = $targetAmount;
       $Contribution->verified = $verified;
       $Contribution->id = $id;

       $Contribution->save();

       return response()->json(['success' => true, 'message' => 'fundraiser verified/updated successfully.']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
         // dd($request);
         $rules = [
            'id' => 'required',

        ];

        $input     = $request->only('id');
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()]);
        }



        $contribution = Contribution::find($request->id);
        if(!$contribution){
            return response()->json(['success' => false, 'response' => 'no contribution matching the id']);
        }

        $contribution->delete();
        return response()->json(['success' => true, 'response' => 'contribution deleted successfully']);
    }

    public function completed(){

        $C = CompletedTransaction::all();

        return response()->json(['success' => true, 'response' => $C]);
    }

    public function pending(){

        $C = PendingTransaction::all();

        return response()->json(['success' => true, 'response' => $C]);
    }


    public function all(){

        $C = callback::all();

        return response()->json(['success' => true, 'response' => $C]);
    }

    public function search(Request $request){

        $rules =
            ['title'=> 'required',

            ]
        ;

        $input     = $request->only('title');
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()]);
        }

        $C = Contribution::where('title','LIKE','%'.$request->title.'%')->get();

        return response()->json(['success' => true, 'response' => $C]);
    }
}
