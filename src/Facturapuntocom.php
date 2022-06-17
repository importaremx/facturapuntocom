<?php

namespace Importaremx\Facturapuntocom;

use Illuminate\Validation\Rule;

class Facturapuntocom
{
    public $api_key;
    public $secret_key;
    public $fplugin;

    public $is_sandbox;

    public $path_pdf;
    public $path_xml;
    
    private $url_sandbox = "http://devfactura.in/api/";
    private $url_production = "https://factura.com/api/";

    private $url;

    private $catalog_types = [
        "Aduana",
        "ClaveUnidad",
        "FormaPago",
        "Impuesto",
        "MetodoPago",
        "Moneda",
        "Pais",
        "RegimenFiscal",
        "Relacion",
        "UsoCfdi",
    ];

    private $rules_for_client;
    private $rules_for_cfdi;
    private $rules_for_related_cfdis;
    private $rules_for_concepts;
    private $rules_for_concept_parts;
    private $rules_for_taxes;
    private $rules_for_transferred_taxes;
    private $rules_for_withheld_taxes;
    private $rules_for_local_taxes;

    private $tipos_documento = [
        "factura",
        "factura_hotel",
        "honorarios",
        "nota_cargo",
        "donativos",
        "arrendamiento",
        "nota_credito",
        "nota_devolucion",
        "carta_porte"
    ];

    private $impuestos_locales = [
        "CEDULAR","ISH"
    ];

    private $catalogo_unidad_medida;
    private $catalogo_uso_cfdi;
    private $catalogo_forma_pago;
    private $catalogo_metodo_pago;
    private $catalogo_tipo_relacion;
    private $catalogo_moneda;
    private $series_disponibles;

    private $serie;

    private $catalogo_impuesto_traslados = [
        "002" => "IVA",
        "003" => "IEPS"
    ];

    private $catalogo_impuesto_retenciones = [
        "001" => "ISR",
        "002" => "IVA",
    ];

    private $catalogo_tipo_factor = [
        "Tasa",
        "Cuota",
        "Exento",
    ];

    public function usarURLSandboxFacturacion()
    {
        return $this->url_sandbox;
    }

    public function usarURLFacturacion()
    {
        return $this->url_production;
    }

    private function loadCatalogs(){

        if(empty($this->serie)){
            $this->serie = config('facturapuntocom.serie_default',1);    
        }
        
        //Cargar las series disponibles en la cuenta
        $series = $this->getSeries();

        if($series->status){
            foreach ($series->data as $key => $value) {
                $this->series_disponibles[$value->SerieID] = $value->SerieName;
            }
        }

        //Salirse si ya se han cargado previamente
        if(!empty($this->catalogo_unidad_medida)){
            return;
        }

        $catalogos_a_cargar = [
            "ClaveUnidad" => "catalogo_unidad_medida",
            "UsoCfdi" => "catalogo_uso_cfdi",
            "FormaPago" => "catalogo_forma_pago",
            "MetodoPago" => "catalogo_metodo_pago",
            "Relacion" => "catalogo_tipo_relacion",
            "Moneda" => "catalogo_moneda",
        ];

        /*CARGAR LOS CATALOGOS DE MEDIDA PARA LAS VALIDACIONES*/
        foreach($catalogos_a_cargar as $nombre_catalogo => $variable){
     
            $datos_catalogo = $this->getCatalog($nombre_catalogo);

            $nombre_cache_catalogo = "cat_".$nombre_catalogo;

            if(\Cache::has($nombre_cache_catalogo)){
                
                $this->{$variable} = (array) json_decode(\Cache::get($nombre_cache_catalogo));
            
            }else{

                foreach($datos_catalogo->data as $dato_cat){
         
                    $this->{$variable}[$dato_cat->key.""] = $dato_cat->name;
                }

                $this->saveToCache($nombre_cache_catalogo,json_encode($this->{$variable}));
            }

        }

        $this->rules_for_cfdi = [

            "Receptor" => "array|required",
            "Receptor.UID" => "string|required",
            "Receptor.ResidenciaFiscal" => "string",
            "TipoDocumento" => [
                "required",
                "in:".implode(",",$this->tipos_documento)
            ],

            "Conceptos" => "array|required|size:1",
            
            "UsoCFDI" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_uso_cfdi))
            ],

            "Serie" => [
                "integer",
                "required",
                "in:".implode(",",array_keys($this->series_disponibles))
            ],
            "FormaPago" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_forma_pago))
            ],
            "MetodoPago" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_metodo_pago))
            ],
            "CondicionesDePago" => "string|required|min:1:max:1000",
            "CfdiRelacionados" => "array",

            "Moneda" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_moneda))
            ],
            "NumOrder" => "string",
            "Fecha" => "date",
            "Comentarios" => "string",
            "Cuenta" => "string",
            "EnviarCorreo" => "boolean",
            "LugarExpedicion" => "string,size:5"
        ];

        $this->rules_for_related_cfdis = [
            "TipoRelacion" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_tipo_relacion))
            ],
            "UUID" => "array|required",
        ];

        $this->rules_for_concepts = [

            "ClaveProdServ" => "string|required",
            "NoIdentificacion" => "string",
            "Cantidad" => "integer|required",
            "ClaveUnidad" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_unidad_medida))
            ],
            "Unidad" => [
                "string",
                "required",
                "in:".implode(",",array_values($this->catalogo_unidad_medida))
            ],
            "ValorUnitario" => "numeric|required",
            "Descripcion" => "string|required",
            "Descuento" => "string",
            
            "Impuestos" => "array|required|size:1",

            "NumeroPedimento" => "string",
            "Predial" => "string",
            
            "Partes" => "array",
        ];

        $this->rules_for_concept_parts = [
            "ClaveProdServ" => "string|required",
            "NoIdentificacion" => "string",
            "Cantidad" => "integer|required",
            "unidad" => [
                "string",
                "required",
                "in:".implode(",",array_values($this->catalogo_unidad_medida))
            ],
            "ValorUnitario" => "numeric|required",
            "Descripcion" => "string|required",
        ];

        $this->rules_for_taxes = [

            "Traslados" => "array|required",
            "Retenidos" => "array",
            "Locales" => "array",
        ];        

        $this->rules_for_transferred_taxes = [

            "Base" => "numeric|required",
            "Impuesto" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_impuesto_traslados))
            ],
            "TipoFactor" => [
                "string",
                "required",
                "in:".implode(",",$this->catalogo_tipo_factor)
            ],
            "TasaOCuota" => "numeric|required",
            "Importe" => "numeric|required|min:1",
        ];

        $this->rules_for_withheld_taxes = [

            "Base" => "numeric|required",
            "Impuesto" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_impuesto_retenciones))
            ],
            "TipoFactor" => [
                "string",
                "required",
                "in:".implode(",",$this->catalogo_tipo_factor)
            ],
            "TasaOCuota" => "numeric|required",
            "Importe" => "numeric|required|min:1",            
        ];

        $this->rules_for_local_taxes = [

            "Impuesto" => [
                "string",
                "required",
                "in:".implode(",",$this->impuestos_locales)
            ],
            "TasaOCuota" => "numeric|required",
        ];

        $this->rules_for_arrays = [

            "Conceptos" => [
                "rules" => $this->rules_for_concepts,
                "Impuestos" => [
                    "rules" => $this->rules_for_taxes,
                    "Traslados" => [
                        "rules" => $this->rules_for_transferred_taxes
                    ],
                    "Retenidos" => [
                        "rules" => $this->rules_for_withheld_taxes
                    ],
                    "Locales" => [
                        "rules" => $this->rules_for_local_taxes
                    ],
                ],
                "Partes" => [
                    "rules" => $this->rules_for_concept_parts
                ]
            ],
            "CfdiRelacionados" => [
                "rules" => $this->rules_for_related_cfdis
            ]
        ];

    }

    public function __construct($is_sandbox = null){

        if(empty($is_sandbox)){
            $is_sandbox = config('facturapuntocom.is_sandbox',true);
        }
        
        $this->api_key = config($is_sandbox ? 'facturapuntocom.api_key_sandbox' : 'facturapuntocom.api_key','');
        $this->secret_key = config($is_sandbox ? 'facturapuntocom.secret_key_sandbox' : 'facturapuntocom.secret_key','');
        
        $this->fplugin = config('facturapuntocom.fplugin','');
        $this->is_sandbox = $is_sandbox;

        $this->path_pdf = config('facturapuntocom.path_pdf','');
        $this->path_xml = config('facturapuntocom.path_xml','');

        $this->url = $this->is_sandbox ? $this->url_sandbox : $this->url_production;

        $this->rules_for_client = [
            "nombre" => "string",
            "apellidos" => "string",
            "email" => "email|required",
            "email2" => "email",
            "email3" => "email",
            "telefono" => "string",
            "razons" => "string",
            "rfc" => "string|required",
            "calle" => "string",
            "numero_exterior" => "integer",
            "numero_interior" => "integer",
            "codpos" => "integer|required|min:5",
            "colonia" => "string",
            "estado" => "string",
            "ciudad" => "string",
            "pais" => "string",
            "numregidtrib" => "string",
            "usocfdi" => "string",
        ];

    }

    private function validateData($data,$schema){

        $validator = \Validator::make($data, $schema);

        if ($validator->fails()) {

            $errors = [];
            
            foreach($validator->errors()->getMessages() as $attribute_errors){
                
                foreach($attribute_errors as $error){

                    $errors[] = $error;
                
                }
            }

            return [false,$errors];
        }else{
            //return [true,$validator->validated()];
            return [true,$data];
        }
    }

    private function getHeaders(){
        return [
            "Content-Type" => "application/json",
            "F-PLUGIN" => $this->fplugin,
            "F-Api-Key" => $this->api_key,
            "F-Secret-Key" => $this->secret_key
        ];
    }

    private function sendRequest($type = 'GET',$url = '',$data = null,$response_type = 'json'){
        
        try{

            $client = new \GuzzleHttp\Client();

            $requestbody = [
                "headers" => $this->getHeaders()
            ];

            if(!empty($data)){
                $requestbody["body"] = json_encode($data);
            }

            $response = $client->request($type, $this->url.$url ,$requestbody);

            if(in_array($response->getStatusCode(),[200,201])){

                if($response_type == "file"){

                    return $response->getBody()->getContents();

                }else if($response_type == "json"){

                    $results = json_decode($response->getBody()->getContents());

                    $status = false;
                    
                    if(isset($results->response)){
                        $status = $results->response;
                    }else if(isset($results->status)){
                        $status = $results->status;
                    }

                    $dataresponse = null;

                    if(isset($results->data)){
                        $dataresponse = $results->data;
                    }else if(isset($results->Data)){
                        $dataresponse = $results->Data;
                    }else{
                        $dataresponse = (array)$results;
                        unset($dataresponse["response"]);
                        unset($dataresponse["message"]);
                    }   

                    if($status == "success"){

                        return $this->response(true,"Request exitoso",$dataresponse);

                    }else{
                        $message = (is_string($results->message)) 
                            ? $results->message
                            : ((is_string($results->message->message))
                                ? $results->message->message
                                : $results->message); 
                        return $this->response(false,$message);
                    }
                }

            }else{
                return $this->response(false,"La respuesta del servidor no es exitosa");
            }


        }catch(\Exception $ex){
            return $this->response(false,$ex->getMessage());
        }
    }

    public function response($status,$message = "",$data = null){
        return new ClientResponse($status,$message,$data);
    }

    public function getCatalog($catalog_type){

        if(!in_array($catalog_type,$this->catalog_types)){
            return $this->response(false,"The catalog does not exists");
        }
    
        return $this->sendRequest('GET',"v3/catalogo/".$catalog_type);           

    }

    public function getClients($rfc = ""){

        return $this->sendRequest('GET',"v1/clients/".$rfc);           

    }

    public function getSeries(){

        if(\Cache::has('cat_series')){

            return $this->response(true,"Series tomados de la caché",(array) json_decode(\Cache::get('cat_series')));

        }else{

            $results = $this->sendRequest('GET',"v1/series");

            if($results->status){

                $this->saveToCache('cat_series',json_encode($results->data));

            }
        }
    }

    public function saveToCache($clave,$value){

        $expiresAt = \Carbon\Carbon::now()->addDays(15);
        
        \Cache::put($clave, $value, $expiresAt);
    }

    public function createClient($data){

        list($resultado,$resultados_validacion) = $this->validateData($data,$this->rules_for_client);

        if($resultado){


            //$is_in_lco = $this->checkLCO($data["rfc"]);

            //if($is_in_lco->status){
                
                return $this->sendRequest('POST',"v1/clients/create",$resultados_validacion);
            
            /*}else{

                return $this->response(false,"El RFC no existe en las listas del SAT");    
            }*/

        }else{

            return $this->response(false,"Errores de validacion",$resultados_validacion);

        }

    }


    public function sendMailCFDI($uid){

        return $this->sendRequest('GET',"v3/cfdi33/".$uid."/email");

    }

    public function sendCancelRequestCFDI($uid){

        return $this->sendRequest('GET',"v3/cfdi33/".$uid."/cancel");

    }

    public function checkCancelRequestStatusCFDI($uid){

        return $this->sendRequest('GET',"v3/cfdi33/".$uid."/cancel_status");

    }

    public function createCfdi($data){

        //Cargar los catalogos para validacion
        $this->loadCatalogs();
        
        //Validar que la serie del CFDI exista
        if(!in_array($this->serie,array_keys($this->series_disponibles))){
            return $this->response(false,"La serie indicada es inválida para la cuenta");            
        }

        $data["Serie"] = $this->serie;

        list($resultado,$resultados_validacion) = $this->validateData($data,$this->rules_for_cfdi);

        //Validacion para arrays dentro de la variable $data
        $errors = [];
        $errors = $this->validateArrays("Conceptos",$data["Conceptos"]);


        if($resultado && empty($errors)){

            return $this->sendRequest('POST',"v3/cfdi33/create",$resultados_validacion);

        }else{

            $resultados_validacion = (!$resultado) 
                ? array_merge($resultados_validacion,$errors)
                : $errors;

            return $this->response(false,"Errores de validacion",$resultados_validacion);

        }

    }

    public function validateArrays($nodo,$data){

        $blocks = explode(".", $nodo);
        
        $validaciones = $this->rules_for_arrays;
        foreach ($blocks as $block) {
            $validaciones = $validaciones[$block];
        }
        
        $results = [];


        if($this->isAssociativeArray($data)){
            $data = [$data];
        }

        foreach($data as $item_data){

            foreach($validaciones as $key => $validacion){

                if($key == "rules"){

                    if(!empty($item_data)){

                        list($resultado,$resultados_validacion) = $this->validateData($item_data,$validacion);
                        if(!$resultado){

                            $results = array_merge($results,$resultados_validacion);

                        }
                    }

                }else{

                    if(!empty($item_data[$key])){

                        $results = $this->validateArrays($nodo.".".$key,$item_data[$key]);
                    }                

                }

            }
        }

        return $results;

    }

    public function updateClient($uid,$data){

        list($resultado,$resultados_validacion) = $this->validateData($data,$this->rules_for_client);

        if($resultado){
            
            return $this->sendRequest('POST',"v1/clients/".$uid."/update",$resultados_validacion);

        }else{

            return $this->response(false,"Errores de validacion",$resultados_validacion);

        }

    }

    public function getRepeatedClients($rfc){

        return $this->sendRequest('GET',"v1/clients/rfc/".$rfc);           

    }

    public function checkLCO($rfc){

        return $this->sendRequest('GET',"v1/clients/lco/".$rfc);           

    }

    public function downloadPdfCFDI($uid){

        if(empty($this->path_pdf)){
            return $this->response(false,"La carpeta para almacenar PDF no está configurada correctamente");
        }

        $results = $this->downloadFile($uid,"pdf");

        if($results->status){

            $filepath = $this->path_pdf.'/'.$uid.'.pdf';

            \Storage::put($filepath, $results->message);

            return $this->response(true,$filepath);

        }else{

            return $results;
        }

    }

    public function downloadXmlCFDI($uid){

        if(empty($this->path_xml)){
            return $this->response(false,"La carpeta para almacenar XML no está configurada correctamente");
        }

        $results = $this->downloadFile($uid,"xml");

        if($results->status){

            $filepath = $this->path_xml.'/'.$uid.'.xml';

            \Storage::put($filepath, $results->message);

            return $this->response(true,$filepath);

        }else{
            
            return $results;
        }

    }

    private function downloadFile($uid,$type){
        
        $results = $this->sendRequest('GET',"v3/cfdi33/".$uid."/".$type,null,"file");

        if(!empty($results)){

            return $this->response(true,$results);

        }else{
            
            return $this->response(false,"No se obtuvo una respuesta correcta del servidor");   
        
        }
    }

    private function isAssociativeArray(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}