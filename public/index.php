<?php
declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use App\Core\App;

$app = new App(dirname(__DIR__));

$r = $app->router;

// Home / health
$r->get('/',         [\App\Controllers\HomeController::class, 'index']);
$r->get('/healthz',  [\App\Controllers\HomeController::class, 'health']);
$r->post('/locale',  [\App\Controllers\HomeController::class, 'setLocale']);

// Auth
$r->get('/login',    [\App\Controllers\AuthController::class, 'showLogin']);
$r->post('/login',   [\App\Controllers\AuthController::class, 'doLogin']);
$r->post('/logout',  [\App\Controllers\AuthController::class, 'logout']);

// Forms (admin / user)
$r->get('/forms',                  [\App\Controllers\FormsController::class, 'index']);
$r->post('/forms',                 [\App\Controllers\FormsController::class, 'create']);
$r->get('/forms/{id}/edit',        [\App\Controllers\FormsController::class, 'edit']);
$r->post('/forms/{id}/delete',     [\App\Controllers\FormsController::class, 'delete']);
$r->post('/forms/{id}/publish',    [\App\Controllers\FormsController::class, 'publish']);
$r->post('/forms/{id}/unpublish',  [\App\Controllers\FormsController::class, 'unpublish']);
$r->post('/forms/{id}/close',      [\App\Controllers\FormsController::class, 'close']);
$r->post('/forms/{id}/duplicate',  [\App\Controllers\FormsController::class, 'duplicate']);
$r->get('/forms/{id}/preview',     [\App\Controllers\FormsController::class, 'preview']);

// Form schema API
$r->get('/api/forms/{id}',  [\App\Controllers\FormApiController::class, 'getForm']);
$r->post('/api/forms/{id}', [\App\Controllers\FormApiController::class, 'saveForm']);

// Public fill
$r->get('/f/{id}',  [\App\Controllers\FillController::class, 'show']);
$r->post('/f/{id}', [\App\Controllers\FillController::class, 'submit']);

// Responses
$r->get('/forms/{id}/responses',                [\App\Controllers\ResponsesController::class, 'index']);
$r->get('/forms/{id}/responses/{rid}',          [\App\Controllers\ResponsesController::class, 'show']);
$r->post('/forms/{id}/responses/{rid}/delete',  [\App\Controllers\ResponsesController::class, 'delete']);
$r->get('/forms/{id}/stats',                    [\App\Controllers\ResponsesController::class, 'stats']);
$r->get('/forms/{id}/responses.csv',            [\App\Controllers\ResponsesController::class, 'exportCsv']);

$app->run();
