<?php


use Phalcon\Mvc\Micro;
use Phalcon\Loader;
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Phalcon\Http\Request;




// Use Loader() to autoload our model
$loader = new Loader();

$loader->registerNamespaces(
    [
        'App\Models' => __DIR__ . '/models/',
        require __DIR__ . '/vendor/autoload.php',
    ]
);

$loader->register();

$di = new FactoryDefault();

// Set up the database service
$di->set(
    'db',
    function () {
        return new PdoMysql(
            [
                'host'     => 'localhost',
                'username' => 'root',
                'password' => '',
                'dbname'   => 'phalconlogin',
            ]
        );
    }
);

$app = new Micro($di);


//get all users
$app->get(
    '/api/users',
    function () use ($app) {
        $jwt = $app->request->getHeader("AUTHORIZATION");
        $key  = base64_decode('quangbui205999');
        $jwt = strstr($jwt,'eyJ0');


        if($jwt)
        {
            try {
                $decoded = JWT::decode($jwt, $key, array('HS256'));
                $phql = 'SELECT * FROM App\Models\Users ORDER BY name';
                $users = $app->modelsManager->executeQuery($phql);

                $data = [];

                foreach ($users as $user) {
                    $data[] = [
                        'id'   => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ];
                }

                echo json_encode($data);
            }
            catch (Exception $e)
            {
                // set response code
                http_response_code(401);

                // show error message
                echo json_encode(array(
                    "message" => "Access denied.",
                    "error" => $e->getMessage()));
            }

        }
        // show error message if jwt is empty
        else{

            // set response code
            http_response_code(401);

            // tell the user access denied
            echo json_encode(array("message" => "Access denied."));
        }

    }
);

//get user by Id
$app->get(
    '/api/users/{id:[0-9]+}',
    function ($id) use ($app) {
        $jwt = $app->request->getHeader("AUTHORIZATION");
        if($jwt)
        {
            // Operation to fetch robot with id $id
            $phql = 'SELECT * FROM App\Models\Users WHERE id = :id:';

            $user = $app->modelsManager->executeQuery(
                $phql,
                [
                    'id' => $id,
                ]
            )->getFirst();

            // Create a response
            $response = new Response();

            if ($user === false) {
                $response->setJsonContent(
                    [
                        'status' => 'NOT-FOUND'
                    ]
                );
            } else {
                $response->setJsonContent(
                    [
                        'data'   => [
                            'id'   => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'password' => $user->password,
                        ]
                    ]
                );
            }

            return $response;
        }
        else{

            // set response code
            http_response_code(401);

            // tell the user access denied
            echo json_encode(array("message" => "Access denied."));
        }
    }
);

//create users
$app->post(
    '/api/users',
    function () use ($app) {
        // Operation to create a fresh robot
//        $robot = $this->request->getJsonRawBody();
        $user = $app->request->getPost();


        $phql = 'INSERT INTO App\Models\Users (name, email , phone, password ) VALUES (:name:, :email:, :phone:, :password:)';

        $status = $app->modelsManager->executeQuery(
            $phql,
            [
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password' => $this->security->hash($user['password']),
            ]
        );

        // Create a response
        $response = new Response();

        // Check if the insertion was successful
        if ($status->success() === true) {
            // Change the HTTP status
            $response->setStatusCode(201, 'Created');

            $robot['id']= $status->getModel()->id;

            $response->setJsonContent(
                [
                    'status' => 'OK',
                    'data'   => $robot,
                ]
            );
        } else {
            // Change the HTTP status
            $response->setStatusCode(409, 'Conflict');

            // Send errors to the client
            $errors = [];

            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }

            $response->setJsonContent(
                [
                    'status'   => 'ERROR',
                    'messages' => $errors,
                ]
            );
        }

        return $response;
    }
);

//login user
$app->post(
    '/api/users/login',
    function () use ($app)
    {
        $email = $app->request->getPost('email');
        $password = $app->request->getPost('password');
        $phql = 'SELECT * FROM App\Models\Users WHERE email = :email:';
        $user = $app->modelsManager->executeQuery(
            $phql,
            [
                'email' => $email,
            ]
        )->getFirst();

        if($user)
        {
            if($this->security->checkHash($password, $user->password))
            {
                $key  = base64_decode('quangbui205999');
                $time = time();
                $expires = $time;
                $token = [
                    'iss' =>  $this->request->getURI(),
                    'iat' =>  $time,
                    'exp' =>  $expires + 86400,
                    'data' =>[
                        'id' => $user->id,
                        'email' => $user->email,
                    ]
                ];

                // set response code
                http_response_code(200);

                // generate jwt
                $jwt = JWT::encode($token, $key);

                echo json_encode(
                    array(
                        "message" => "Successful login.",
                        "jwt" => $jwt
                    )
                );


//                var_dump($jwt);
//                die();
//                return 'Login Success!';
//                return $this->respondWithArray($this->userService->createJwtToken($user));
//                $this->session->set('auth',['userName' => $user->name]);
//                $this->session->set('user',$user);
//                try {
//                    if (!$token = JWT::attempt($credentials)) {
//                        return response()->json(['invalid_email_or_password'], 422);
//                    }
//                } catch (JWTException $e) {
//                    return response()->json(['failed_to_create_token'], 500);
//                }
            }
            else{
                echo 'Password Wrong';
            }

        }
        else
        {
            var_dump('False');
            die;
        }

    }
);

// Updates user based on primary key
$app->post(
    '/api/users/{id:[0-9]+}',
    function ($id) use ($app) {
        $jwt = $app->request->getHeader("AUTHORIZATION");
        if($jwt)
        {
            // Operation to update a robot with id $id
            $user = $app->request->getPost();

            $phql = 'UPDATE App\Models\Users SET name = :name:, email = :email:, phone = :phone:, password = :password: WHERE id = :id:';

            $status = $app->modelsManager->executeQuery(
                $phql,
                [
                    'id'   => $id,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'password' => $this->security->hash($user['password']),

                ]
            );

            // Create a response
            $response = new Response();

            // Check if the insertion was successful
            if ($status->success() === true) {
                $response->setJsonContent(
                    [
                        'status' => 'Update Success'
                    ]
                );
            } else {
                // Change the HTTP status
                $response->setStatusCode(409, 'Conflict');

                $errors = [];

                foreach ($status->getMessages() as $message) {
                    $errors[] = $message->getMessage();
                }

                $response->setJsonContent(
                    [
                        'status'   => 'ERROR',
                        'messages' => $errors,
                    ]
                );
            }

            return $response;
        }
        // show error message if jwt is empty
        else{

            // set response code
            http_response_code(401);

            // tell the user access denied
            echo json_encode(array("message" => "Access denied."));
        }

    }
);

// Deletes users based on primary key
$app->delete(
    '/api/users/{id:[0-9]+}',
    function ($id) use ($app) {
        // Operation to delete the robot with id $id
        $phql = 'DELETE FROM App\Models\Users WHERE id = :id:';

        $status = $app->modelsManager->executeQuery(
            $phql,
            [
                'id' => $id,
            ]
        );

        // Create a response
        $response = new Response();

        if ($status->success() === true) {
            $response->setJsonContent(
                [
                    'status' => 'OK'
                ]
            );
        } else {
            // Change the HTTP status
            $response->setStatusCode(409, 'Conflict');

            $errors = [];

            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }

            $response->setJsonContent(
                [
                    'status'   => 'ERROR',
                    'messages' => $errors,
                ]
            );
        }

        return $response;
    }
);

$app->handle();