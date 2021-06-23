<?php

return [
    'api_key' =>  env('FACTURACOM_API_KEY', ''),    
    'secret_key' =>  env('FACTURACOM_SECRET_KEY', ''),
    'fplugin' =>  env('FACTURACOM_FPLUGIN', '9d4095c8f7ed5785cb14c0e3b033eeb8252416ed'),
    'is_sandbox' => env('FACTURACOM_SANDBOX',true),
    'serie_default' => env('FACTURACOM_SERIE_DEFAULT',1),
    'path_pdf' => env('FACTURACOM_PATH_PDF',"cfdi_files/pdf"),
    'path_xml' => env('FACTURACOM_PATH_XML',"cfdi_files/xml"),
    'queue_connection' => env('FACTURACOM_QUEUE_CONNECTION',""),
    'queue_name' => env('FACTURACOM_QUEUE_NAME',""),

    
];