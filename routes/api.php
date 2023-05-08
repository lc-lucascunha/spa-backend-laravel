<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
SUCCESSO

200 OK
Indica que a solicitação foi bem-sucedida.

201 criado
Indica que a solicitação foi bem-sucedida e um novo recurso foi criado como resultado.

ERROS DO CLIENTE

400 Solicitação inválida
A solicitação não pôde ser compreendida pelo servidor devido à sintaxe incorreta.
O cliente NÃO DEVE repetir a solicitação sem modificações.

401 não autorizado
Indica que a solicitação requer informações de autenticação do usuário.
O cliente PODE repetir a solicitação com um campo de cabeçalho de autorização adequado

*/

function formatResponseError($errors){
    $errors = $errors->toArray();
    $data = [];
    foreach ($errors as $error){
        foreach ($error as $value){
            $data[] = $value;
        }
    }
    return $data;
}

// AUTENTICAÇÃO (LOGIN+SENHA) RETORNANDO TOKEN

Route::post('/auth', function (Request $request){

    $validate = Validator::make($request->all(), [
        'email'    => 'required|string|email|max:100',
        'password' => 'required|string',
    ]);

    if($validate->fails()){
        return response()->json(formatResponseError($validate->errors()), 400);
    }

    if(Auth()->attempt(['email'=>$request->email, 'password' => $request->password])){
        $user = auth()->user();
        $user->file  = asset($user->file);
        $user->token = $user->createToken($user->email)->accessToken;
        return response()->json($user, 200);
    }
    else{
        return response()->json(['Email ou Senha incorretos.'], 400);
    }
});

// CADASTRO DE USUÁRIO

Route::post('/user', function (Request $request){

    $validate = Validator::make($request->all(), [
        'name'     => 'required|string|max:100',
        'email'    => 'required|string|email|max:100|unique:users',
        'password' => 'required|string|min:6',
    ]);

    if($validate->fails()){
        return response()->json(formatResponseError($validate->errors()), 400);
    }

    $user = User::create([
        'name'     => $request->name,
        'email'    => $request->email,
        'password' => bcrypt($request->password),
    ]);

    $user->token = $user->createToken($user->email)->accessToken;

    return response()->json($user, 201);
});

// ATUALIZAR USUÁRIO

Route::put('/user', function (Request $request) {
    $user = $request->user();

    $validateRules = [
        'name'     => 'required|string|max:100',
        'email'    => 'required|string|email|max:100|unique:users,email,'.$user->id,
        'password' => 'required|string|min:6',
    ];

    if(!$request->password){
         unset($validateRules['password']);
    }

    $validate = Validator::make($request->all(), $validateRules);

    if($validate->fails()){
        return response()->json(formatResponseError($validate->errors()), 422);
    }

    $dataUpdate = [
        'name'     => $request->name,
        'email'    => $request->email,
    ];

    if($request->password) {
        $dataUpdate['password'] = bcrypt($request->password);
    }

    if($request->file){

        $time = time();
        $path = 'profile';
        $path .= DIRECTORY_SEPARATOR.$user->id;
        $extension = substr($request->file, 11, strpos($request->file, ';') -11);
        $filename = $time.'.'.$extension;
        $pathFull = $path.DIRECTORY_SEPARATOR.$filename;

        $file = str_replace('data:image/'.$extension.';base64,', '', $request->file);
        $file = base64_decode($file);

        if(!file_exists($path)){
            mkdir($path, 0700);
        }

        file_put_contents($pathFull, $file);

        if($user->file && file_exists($user->file)){
            unlink($user->file);
        }

        $dataUpdate['file'] = $pathFull;
    }

    $user->update($dataUpdate);

    $user->file = asset($user->file);
    $user->token = $user->createToken($user->email)->accessToken;

    return response()->json($user, 200);

})->middleware('auth:api');

