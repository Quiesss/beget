<?php


use Domens\AuthorizationException;
use Domens\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Domens\DomenMapper;
use Domens\User;

require __DIR__ . '/vendor/autoload.php';

try {
    $conf = include 'config/bd.php';
    $bd = $conf['bd'];
    $name = $conf['name'];
    $password = $conf['password'];
    $connection = new PDO($bd, $name, $password);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    echo "Database error: ". $exception->getMessage();
    die();
}

$content = new FilesystemLoader('templates');
$view = new Environment($content);
$domainMapper = new DomenMapper($connection);

$app = AppFactory::create();
//$app->addBodyParsingMiddleware();

$session = new Session();
$sessionMiddleware = function(Request $request, RequestHandler $handler) use ($session) {
    $session->start();
    $response = $handler->handle($request);
    $session->save();

    return $response;
};
$loginNowMiddleware = function (Request $request, RequestHandler $handler) use ($session): Response
{
    $response = $handler->handle($request);
    $sessionData = $session->getData('onsite');
    $getUriNow = $request->getUri()->getPath();
    if(!isset($sessionData)) {
        if ($getUriNow == '/auth/login' || $getUriNow == '/auth/reg') {
            return $response;
        } else {
//        $session->flush('user');
            return $response->withHeader('Location', '/auth/login')
                ->withStatus(302);
        }
    } else {
        return $response;
    }
};
$app->add($loginNowMiddleware);
$app->add($sessionMiddleware);
$app->addErrorMiddleware(true, true, true);
# Аутентификация

$user = new User($connection, $session);

$app->get('/auth/login', function (Request $request, Response $response) use ($view, $session) {
    //var_dump($_SESSION);
    $body = $view->render('login.twig', [
        'msg' => $session->flush('msg')
    ]);
    $response->getBody()->write($body);
    return $response;
});
$app->post('/auth/login', function (Request $request, Response $response) use ($view, $user, $session) {
    $param = (array) $request->getParsedBody();
    try {
        $user->login($param['login'], $param['pass']);
    } catch (AuthorizationException $exception) {
        $session->setData('msg', $exception->getMessage());
        return $response->withHeader('Location', '/auth/login');
    }
    $session->setData('user_login', $param['login']);
    return $response->withHeader('Location', '/dashboard');
});
$app->get('/auth/reg', function (Request $request, Response $response) use ($view, $session) {

    $body = $view->render('reg.twig', [
        'msg' => $session->flush('msg')
    ]);
    $response->getBody()->write($body);
    return $response;
});
$app->post('/auth/create', function (Request $request, Response $response) use ($view, $session, $user) {
    $param = (array) $request->getParsedBody();
    try {
        $SignUp = $user->create($param['login'], $param['pass']);
    } catch (AuthorizationException $exception) {
        $session->setData('msg', $exception->getMessage());
        return $response->withHeader('Location', '/auth/reg');
    }
    $body = $view->render('reg.twig', [
        'content' => $SignUp
    ]);
    $response->getBody()->write($body);
    return $response;
});



$app->get('/dashboard', function (Request $request, Response $response) use ($view, $domainMapper, $session, $connection) {
    $userLogin = $session->getData('user_login');
    $domainlist = $domainMapper->GetByDomens($userLogin);
    $body = $view->render('dashboard.twig', [
        'domenlist' => $domainlist,
        'userLogin' => $session->getData('user_login'),
        'userId' => $session->getData('user_id')
    ]);
    $response->getBody()->write($body);
    return $response;
});

# Мидлвар для проверки авторизации

//$mw = function (Request $request, RequestHandler $handler) use ($app) {
//    if ($app->get('/login'))
//    $response = $handler->handle($request)->withHeader('Location', '/login');
//    return $response;
//};
//$app->add($mw);

$app->run();