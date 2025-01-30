<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Logger;
use App\Core\ExceptionHandler;
use App\Config\Environment;

Environment::load(__DIR__ . '/.env');

ExceptionHandler::register();

$router = new Router();

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

// Adiciona log para debug
Logger::debug('Original URI', ['uri' => $uri]);

// Adiciona log após remoção do prefixo
Logger::info('URI after prefix removal', ['uri' => $uri]);

// Adiciona log do método HTTP
Logger::info('Request method', ['method' => $method]);

$uri = explode('/', $uri);

// Se a URI estiver vazia após remover o prefixo, use um array vazio
if (empty($uri[0])) {
    $uri = [];
}

$jsonBody = file_get_contents('php://input');
$params = [];

if (!empty($jsonBody)) {
    $request = json_decode($jsonBody, true);
    Logger::debug('JSON body parsing', [
        'raw_body' => $jsonBody,
        'decoded' => $request,
        'json_last_error' => json_last_error(),
        'json_last_error_msg' => json_last_error_msg()
    ]);
    
    if ($request === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    $params = is_array($request) ? ($request['params'] ?? $request) : [];
    
    Logger::debug('Parsed params', [
        'params' => $params,
        'request' => $request
    ]);
}

Logger::debug('Request received', [
    'uri' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'input' => file_get_contents('php://input')
]);

Logger::debug('Incoming request', [
    'method' => $method,
    'uri' => $_SERVER['REQUEST_URI'],
    'parsedUri' => $uri
]);

if (count($uri) >= 1) {
    $controller = $uri[0];
    
    $routePath = implode('/', $uri);
    
    try {
        // Add debug log for request body
        Logger::debug('Request body received', [
            'raw_body' => $jsonBody,
            'decoded_params' => $params,
            'request_uri' => $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD']
        ]);

        $controllerMap = [
            'machines' => 'Machine',
            'accounts' => 'Account',
            'companies' => 'Companies',
            'incidents' => 'Incidents',
            'licensing' => 'Licensing',
            'network' => 'Network',
            'packages' => 'Packages',
            'policies' => 'Policies',
            'push' => 'Push',
            'quarantine' => 'Quarantine',
            'reports' => 'Reports',
            'sync' => 'Sync',
            'integrations' => 'Integrations',
            'apikeys' => 'ApiKeys',
            'webhook' => 'Webhook'
        ];

        $controllerBase = $controllerMap[$controller] ?? ucfirst($controller);
        $controllerName = $controllerBase . 'Controller';
        $controllerClass = "App\\Controllers\\{$controllerName}";
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller '{$controllerName}' not found", 404);
        }


        $routeRegistered = false;


        switch ($controller) {
            case 'machines':
                $router->addRoute('POST', 'machines/inventory', 'SyncController', 'getMachineInventory');
                $router->addRoute('GET', 'machines/list', 'MachineController', 'listMachineData');
                $routeRegistered = true;
                break;

            case 'sync':
                $router->addRoute('POST', 'sync/syncAll', 'SyncController', 'syncAll');
                $router->addRoute('POST', 'sync/accounts/filter', 'SyncController', 'getFilteredAccounts');
                $router->addRoute('POST', 'sync/companies/filter', 'SyncController', 'getFilteredCompanies');
                $router->addRoute('POST', 'sync/incidents/filter', 'SyncController', 'getFilteredIncidents');
                $router->addRoute('POST', 'sync/integrations/filter', 'SyncController', 'getFilteredIntegrations');
                $router->addRoute('POST', 'sync/licenses/filter', 'SyncController', 'getFilteredLicenses');
                $router->addRoute('POST', 'sync/machines/filter', 'SyncController', 'getFilteredMachines');
                $router->addRoute('POST', 'sync/networks/filter', 'SyncController', 'getFilteredNetworks');
                $router->addRoute('POST', 'sync/packages/filter', 'SyncController', 'getFilteredPackages');
                $router->addRoute('POST', 'sync/policies/filter', 'SyncController', 'getFilteredPolicies');
                $router->addRoute('POST', 'sync/push/filter', 'SyncController', 'getFilteredPushSettings');
                $router->addRoute('POST', 'sync/quarantine/filter', 'SyncController', 'getFilteredQuarantineItems');
                $router->addRoute('POST', 'sync/reports/filter', 'SyncController', 'getFilteredReports');
                $routeRegistered = true;
                break;

            case 'accounts':
                $router->addRoute('GET', 'accounts/list', 'AccountController', 'getAccountsList');
                $router->addRoute('POST', 'accounts/createAccount', 'AccountController', 'createAccount');
                $router->addRoute('PUT', 'accounts/updateAccount', 'AccountController', 'updateAccount');
                $router->addRoute('DELETE', 'accounts/deleteAccount', 'AccountController', 'deleteAccount');
                $routeRegistered = true;
                break;

            case 'companies':
                $router->addRoute('GET', 'companies/getCompanyDetails', 'CompaniesController', 'getCompanyDetails');
                $router->addRoute('PUT', 'companies/updateCompanyDetails', 'CompaniesController', 'updateCompanyDetails');
                $routeRegistered = true;
                break;

            case 'incidents':
                $router->addRoute('GET', 'incidents/getBlockListItem', 'IncidentsController', 'getBlockListItem');
                $router->addRoute('GET', 'incidents/getBlocklistItems', 'IncidentsController', 'getBlocklistItems');
                $router->addRoute('POST', 'incidents/addToBlocklist', 'IncidentsController', 'addToBlocklist');
                $router->addRoute('DELETE', 'incidents/removeFromBlocklist', 'IncidentsController', 'removeFromBlocklist');
                $router->addRoute('POST', 'incidents/createIsolateEndpointTask', 'IncidentsController', 'createIsolateEndpointTask');
                $router->addRoute('POST', 'incidents/createRestoreEndpointFromIsolationTask', 'IncidentsController', 'createRestoreEndpointFromIsolationTask');
                $routeRegistered = true;
                break;

            case 'licensing':
                $router->addRoute('GET', 'licensing/getLicenseInfo', 'LicensingController', 'getLicenseInfo');
                $router->addRoute('GET', 'licensing/getMonthlyUsage', 'LicensingController', 'getMonthlyUsage');
                $router->addRoute('POST', 'licensing/setLicenseKey', 'LicensingController', 'setLicenseKey');
                $routeRegistered = true;
                break;

            case 'network':
                $router->addRoute('POST', 'network/createScanTask', 'NetworkController', 'createScanTask');
                $router->addRoute('GET', 'network/getEndpointsList', 'NetworkController', 'getEndpointsList');
                $router->addRoute('GET', 'network/getManagedEndpointDetails', 'NetworkController', 'getManagedEndpointDetails');
                $router->addRoute('GET', 'network/getCustomGroupsList', 'NetworkController', 'getCustomGroupsList');
                $router->addRoute('GET', 'network/getNetworkInventoryItems', 'NetworkController', 'getNetworkInventoryItems');
                $router->addRoute('GET', 'network/getScanTasksList', 'NetworkController', 'getScanTasksList');
                $router->addRoute('POST', 'network/createCustomGroup', 'NetworkController', 'createCustomGroup');
                $router->addRoute('DELETE', 'network/deleteCustomGroup', 'NetworkController', 'deleteCustomGroup');
                $router->addRoute('POST', 'network/moveEndpoints', 'NetworkController', 'moveEndpoints');
                $router->addRoute('POST', 'network/moveCustomGroup', 'NetworkController', 'moveCustomGroup');
                $router->addRoute('POST', 'network/setEndpointLabel', 'NetworkController', 'setEndpointLabel');
                $router->addRoute('DELETE', 'network/deleteEndpoint', 'NetworkController', 'deleteEndpoint');
                $routeRegistered = true;
                break;

            case 'packages':
                $router->addRoute('GET', 'packages/getInstallationLinks', 'PackagesController', 'getInstallationLinks');
                $router->addRoute('GET', 'packages/getPackagesList', 'PackagesController', 'getPackagesList');
                $router->addRoute('POST', 'packages/createPackage', 'PackagesController', 'createPackage');
                $router->addRoute('DELETE', 'packages/deletePackage', 'PackagesController', 'deletePackage');
                $routeRegistered = true;
                break;

            case 'policies':
                $router->addRoute('GET', 'policies/getPoliciesList', 'PoliciesController', 'getPoliciesList');
                $router->addRoute('GET', 'policies/getPolicyDetails', 'PoliciesController', 'getPolicyDetails');
                $routeRegistered = true;
                break;

            case 'integrations':
                $router->addRoute('GET', 'integrations/getHourlyUsageForAmazonEC2Instances', 'IntegrationsController', 'getHourlyUsageForAmazonEC2Instances');
                $router->addRoute('GET', 'integrations/getAmazonEC2ExternalIdForCrossAccountRole', 'IntegrationsController', 'getAmazonEC2ExternalIdForCrossAccountRole');
                $router->addRoute('POST', 'integrations/configureAmazonEC2IntegrationUsingCrossAccountRole', 'IntegrationsController', 'configureAmazonEC2IntegrationUsingCrossAccountRole');
                $router->addRoute('POST', 'integrations/generateAmazonEC2ExternalIdForCrossAccountRole', 'IntegrationsController', 'generateAmazonEC2ExternalIdForCrossAccountRole');
                $router->addRoute('POST', 'integrations/disableAmazonEC2Integration', 'IntegrationsController', 'disableAmazonEC2Integration');
                $routeRegistered = true;
                break;

            case 'push':
                $router->addRoute('POST', 'push/getPushEventSettings', 'PushController', 'getPushEventSettings');
                $router->addRoute('POST', 'push/setPushEventSettings', 'PushController', 'setPushEventSettings');
                $router->addRoute('POST', 'push/sendTestPushEvent', 'PushController', 'sendTestPushEvent');
                $router->addRoute('POST', 'push/getPushEventStats', 'PushController', 'getPushEventStats');
                $router->addRoute('POST', 'push/resetPushEventStats', 'PushController', 'resetPushEventStats');
                $routeRegistered = true;
                break;

            case 'quarantine':
                $router->addRoute('GET', 'quarantine/getBlocklistItems', 'QuarantineController', 'getBlocklistItems');
                $routeRegistered = true;
                break;

            case 'reports':
                $router->addRoute('GET', 'reports/getReportsList', 'ReportsController', 'getReportsList');
                $router->addRoute('GET', 'reports/getDownloadLinks', 'ReportsController', 'getDownloadLinks');
                $router->addRoute('POST', 'reports/createReport', 'ReportsController', 'createReport');
                $router->addRoute('DELETE', 'reports/deleteReport', 'ReportsController', 'deleteReport');
                $routeRegistered = true;
                break;

            case 'apikeys':
                $router->addRoute('GET', 'apikeys/list', 'ApiKeysController', 'listKeys');
                $router->addRoute('POST', 'apikeys/create', 'ApiKeysController', 'createKey');
                $router->addRoute('PUT', 'apikeys/update', 'ApiKeysController', 'updateKey');
                $router->addRoute('DELETE', 'apikeys/delete', 'ApiKeysController', 'deleteKey');
                $routeRegistered = true;
                break;

            case 'webhook':
                $router->addRoute('POST', 'webhook', 'WebhookController', 'addEvents');
                $router->addRoute('POST', 'webhook/addEvents', 'WebhookController', 'addEvents');
                $router->addRoute('GET', 'webhook', 'WebhookController', 'getEvents');
                $routeRegistered = true;
                break;

            case 'auth':
                $router->addRoute('POST', 'auth/login', 'AuthController', 'login');
                $router->addRoute('POST', 'auth/logout', 'AuthController', 'logout');
                $router->addRoute('GET', 'auth/test', 'AuthController', 'testAuth');
                $router->addRoute('POST', 'auth/create-default-user', 'AuthController', 'createDefaultUser');
                $routeRegistered = true;
                break;

            default:
                throw new \Exception(sprintf(
                    "Route not found: %s %s. Available controllers: %s",
                    $method,
                    $routePath,
                    implode(', ', array_keys($controllerMap))
                ), -32601);
        }

        if (!$routeRegistered) {
            throw new \Exception(sprintf(
                "Route not found: %s %s. This controller exists but the route is not registered.",
                $method,
                $routePath
            ), -32601);
        }

        Logger::debug('Handling request', [
            'method' => $method,
            'controller' => $controller,
            'routePath' => $routePath,
            'params' => $params,
            'decoded_body' => json_decode($jsonBody, true)
        ]);

        $response = $router->handleRequest($method, $routePath, $params);
        
        // Add debug log for response
        Logger::debug('Response being sent', [
            'response' => $response
        ]);

        header('Content-Type: application/json');
        echo $response;

        // Após processar a URI
        Logger::debug('Route processing', [
            'method' => $method,
            'controller' => $controller,
            'routePath' => $routePath,
            'uri' => $uri,
            'params' => $params
        ]);

    } catch (\Exception $e) {
        Logger::error('Request handling failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'method' => $method ?? null,
            'controller' => $controller ?? null,
            'routePath' => $routePath ?? null,
            'params' => $params ?? null
        ]);

        $code = $e->getCode() ?: -32603;
        http_response_code($code === -32601 ? 404 : 500);
        
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $e->getMessage(),
                'data' => [
                    'requestDetails' => [
                        'method' => $method ?? 'unknown',
                        'controller' => $controller ?? 'unknown',
                        'routePath' => $routePath ?? 'unknown'
                    ],
                    'debug' => [
                        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                    ]
                ]
            ],
            'id' => $request['id'] ?? null
        ], JSON_PRETTY_PRINT);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32601,
            'message' => 'Invalid route format',
            'data' => [
                'details' => 'Expected format: /{controller}/{action}',
                'receivedUri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'parsedSegments' => $uri
            ]
        ],
        'id' => $request['id'] ?? null
    ], JSON_PRETTY_PRINT);
}