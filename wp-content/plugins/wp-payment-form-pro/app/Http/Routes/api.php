<?php

/**
 * @var $router WPPayForm\App\Http\Router
 */

// without any policy
$router->prefix('tools/form')->group(function ($router) {
    $router->get('/{id}/export', 'FormController@export')->int('id');
    $router->post('/import', 'FormsController@import');
});

$router->prefix('debug/{type}')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('/', 'GlobalSettingsController@generateDebug')->alpha('type');
});

$router->prefix('file')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->post('/upload', 'GlobalSettingsController@handleFileUpload');
});

$router->prefix('forms')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('/', 'FormsController@index');
    $router->post('/', 'FormsController@store');
    $router->get('/demo', 'FormsController@demo');
    $router->get('/formatted', 'FormsController@formatted');

    $router->prefix('entries')->group(function ($router) {
        $router->delete('/remove', 'SubmissionController@remove');
        $router->put('/{id}/pay-status', 'SubmissionController@paymentStatus');
    });

    $router->prefix('settings')->group(function ($router) {
        $router->get('/currencies', 'GlobalSettingsController@currencies');
        $router->post('/currencies', 'GlobalSettingsController@saveCurrencies');

        $router->get('/stripe', 'GlobalSettingsController@stripe');
        $router->post('/stripe', 'GlobalSettingsController@saveStripe');

        $router->get('/roles', 'GlobalSettingsController@roles');
        $router->post('/roles', 'GlobalSettingsController@setRoles');

        $router->get('/recaptcha', 'GlobalSettingsController@getRecaptcha');
        $router->post('/recaptcha', 'GlobalSettingsController@saveRecaptcha');

        $router->get('/integrations', 'IntegrationController@getGlobalSettings');
        $router->post('/integrations', 'IntegrationController@setGlobalSettings');
    });

    $router->prefix('integration')->group(function ($router) {
        $router->post('/change-status', 'IntegrationController@index');
        $router->post('/enable', 'IntegrationController@enable');
        $router->post('/chained', 'IntegrationController@chained');
    });
});

$router->prefix('form/{id}')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('/', 'FormController@index')->int('id');
    $router->post('/', 'FormController@store')->int('id');
    $router->put('/', 'FormController@update')->int('id');
    $router->delete('/', 'FormController@remove')->int('id');
    $router->post('/duplicate', 'FormController@duplicateForm')->int('id');
    $router->get('/editors', 'FormController@editors')->int('id');

    $router->prefix('/settings')->group(function ($router) {
        $router->get('/', 'FormController@settings')->int('id');
        $router->post('/', 'FormController@saveSettings')->int('id');
        $router->get('/design', 'FormController@designSettings')->int('id');
        $router->post('/design', 'FormController@updateDesignSettings')->int('id');
    });

    $router->prefix('/entries')->group(function ($router) {
        $router->get('/', 'SubmissionController@index')->int('id');
        $router->get('/reports', 'SubmissionController@reports')->int('id');

        $router->prefix('/{entryId}')->group(function ($router) {
            $router->get('/', 'SubmissionController@getSubmission')->int('id', 'entryId');
            $router->post('/notes', 'SubmissionController@addSubmissionNote')->int('id', 'entryId');
            $router->delete('/notes/{noteId}', 'SubmissionController@deleteNote')->int('id', 'entryId', 'noteId');
            $router->post('/status', 'SubmissionController@changeEntryStatus')->int('id', 'entryId');
            $router->get('/navigate', 'SubmissionController@getNextPrevSubmission')->int('id', 'entryId');
        });
    });

    $router->prefix('/integration')->withPolicy('AdminPolicy')->group(function ($router) {
        $router->post('/slack', 'FormController@saveIntegration')->int('id');
        $router->get('/slack', 'FormController@getIntegration')->int('id');

        $router->get('/', 'IntegrationController@getIntegrations')->int('id');

        $router->prefix('/settings')->group(function ($router) {
            $router->get('/', 'IntegrationController@settings')->int('id');
            $router->post('/', 'IntegrationController@saveSettings')->int('id');
            $router->delete('/', 'IntegrationController@deleteSettings')->int('id');
            $router->post('/change-status', 'IntegrationController@status')->int('id');
        });

        $router->get('/lists', 'IntegrationController@lists')->int('id');
    });
});
