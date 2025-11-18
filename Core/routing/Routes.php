<?php

Router::get('/', 'UploadController@index');
Router::post('/upload/import', 'UploadController@import');
