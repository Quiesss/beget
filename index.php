<?php

use DomainsThings\domainLink;
use DomainsThings\uploadDomains;
use Domens\AuthorizationException;
use Domens\DomainException;
use Domens\privatflare;
use Domens\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as Resp;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Domens\DomenMapper;
use Domens\User;
use DomainsThings\DomainThings;
use Psr\Http\Message\UploadedFileInterface;
use UserThings\Settings;


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
    $sessionData = $session->getData('user');
    $getUriNow = $request->getUri()->getPath();
    if(@$sessionData['onsite'] != '1' && @$sessionData['onsite'] != '2'){
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
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function (Request $request) {
        $response = new Resp();
        $response->getBody()->write('404 NOT FOUND');

        return $response->withStatus(404);
    });

# Аутентификация

$user = new User($connection, $session);

$app->get('/auth/login', function (Request $request, Response $response) use ($view, $session) {
    var_dump($_SESSION);
    $body = $view->render('login.twig', [
        'msg' => $session->flush('msg')
    ]);
    $response->getBody()->write($body);
    return $response;
});
$app->post('/auth/login', function (Request $request, Response $response) use ($view, $user, $session) {
    var_dump($_SESSION);
    $param = (array) $request->getParsedBody();
    try {
        $user->login($param['login'], $param['pass']);
    } catch (AuthorizationException $exception) {
        $session->setData('msg', $exception->getMessage());
        return $response->withHeader('Location', '/auth/login');
    }
    //$session->setData('user_login', $param['login']);
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
        return $response->withHeader('Location', '/auth/reg')->withStatus(302);
    }
    $body = $view->render('reg.twig', [
        'content' => $SignUp
    ]);
    $response->getBody()->write($body);
    return $response;
});
$app->get('/auth/logout', function (Request $request, Response $response) use ($view, $session) {
    $session->flush('user');
    $response->withHeader('Location', '/auth/login')->withStatus(302);
    return $response;
});

$app->get('/dashboard', function (Request $request, Response $response) use ($view, $domainMapper, $session, $connection) {
//    var_dump($_SESSION);
    $userData = new DomainThings($connection);
    $countDomains = $userData->getCountDomains();
    $userLogin = $session->getData('user')['user_login'];
    $domainlist = $domainMapper->GetByDomens($userLogin);
    $body = $view->render('dashboard.twig', [
        'domenlist' => $domainlist,
        'msg' => $session->flush('msg'),
        'domain_msg' => $session->flush('domain_msg'),
        'countSuccessDomain' => $session->flush('successCount'),
        'hostMsg' => $session->flush('hostMsg'),
        'anoteMsg' => $session->flush('anoteMsg'),
        'pfMsg' => $session->flush('pfMsg'),
        'countDomains' => $userData->howMuchLeft(),
        'countLinodeDomains' => $userData->getCountDomainsLinode(),
        'countDoDomains' => $userData->getCountDomainsDo(),
        'countUserDomains' => $userData->getCountUserDomains($userLogin)
    ]);
    $response->getBody()->write($body);
    return $response;
});

$app->get('/adminpanel', function (Request $request, Response $response) use ($view, $session) {
//    var_dump($_SESSION);
    if($session->getData('user')['onsite'] == '2') {
        $body = $view->render('adminpanel.twig', [
            'countSuccessUpload' => $session->flush('countSuccessUpload')
        ]);
        $response->getBody()->write($body);
        return $response;
    } else {
    $response->getBody()->write("U not admin ! <a href='/dashboard'>Back</a>");
        return $response;
    }
});
$app->post('/uploadDomains', function (Request $request, Response $response) use ($connection, $session) {
    $param = $request->getParsedBody();
    $directory = __DIR__ . '/files';
    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['uploadFile'];
    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);
        $file = new uploadDomains($connection, $filename);
        $upload = $file->upload($param['host']);
        $session->setData('countSuccessUpload', $upload);
    }
    return $response->withHeader('Location', '/adminpanel')->withStatus(302);
});

$app->get('/privateflare', function (Request $request, Response $response) use ($view, $session) {
    $userLogin = $session->getData('user')['user_login'];
    $dFromPF = new privatflare();
    $viewDomains = $dFromPF->getDomens(1, 'sss');
    $body = $view->render('pf.twig', [
        'domenlist' => $viewDomains,
        'msg' => $session->flush('msg'),
    ]);
    $response->getBody()->write($body);
    return $response;
});

$app->post('/getDomains', function (Request $request, Response $response) use ($connection, $session) {
    $user_login = $session->getData('user')['user_login'];
    $param = (array) $request->getParsedBody();
    $domainThings = new DomainThings($param, $connection);
    try {
        $countSuccessDomains = $domainThings->getDomains($param['countDomains'], $user_login, $param['host']);
        $session->setData('successCount', "Успешно загружено " . $countSuccessDomains);
//      return $response->getBody()->write($countSuccessDomains);
        return $response->withHeader('Location', '/dashboard');
    } catch (DomainException $exception) {
        $session->setData('domain_msg', $exception->getMessage());
        return $response->withHeader('Location', '/dashboard');
    }
});

$app->post('/linkHost', function (Request $request, Response $response) use ($connection, $session) {
    $domain = new domainLink($connection, $session);
    $param = (array) $request->getParsedBody();
    $host = 'link' . $param['host'];
    $linking = $domain->$host($param['domain']);
    echo json_encode($linking);
    //echo $session->flush('hostMsg') . "<br>" . $session->flush('anoteMsg') . "<br>" . $session->flush('pfMsg');
    return $response;

});

$app->get('/settings', function (Request $request, Response $response) use ($view, $session, $connection) {
    $settings = new Settings($connection, $session);
    $LT = $settings->getLinodeToken();
    $DoT = $settings->getDoToken();
    $Anote = $settings->getIpNote();
    $body = $view->render('settings.twig', [
        'notif' => $session->flush('notif'),
        'LinodeToken' => $LT,
        'DoToken' => $DoT,
        'Anote' => $Anote
    ]);
    $response->getBody()->write($body);
    return $response;
});
$app->post('/settings', function (Request $request, Response $response) use ($connection, $session) {
    $param = $request->getParsedBody();
    $settings = new Settings($connection, $session);
    if(isset($param['LinodeToken'])) $changeProcess = $settings->ChangeLinodeToken($param['LinodeToken']);
        elseif(isset($param['DoToken'])) $changeProcess = $settings->ChangeDoToken($param['DoToken']);
            elseif(isset($param['Anote'])) $changeProcess = $settings->ChangeAnote($param['Anote']);
        else $changeProcess = "something wrong !";
        echo $changeProcess;
    return $response;

});


/**
 * @throws Exception
 */
function moveUploadedFile(string $directory, UploadedFileInterface $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);

    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

$app->run();