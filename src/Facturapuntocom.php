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

            foreach($datos_catalogo->data as $dato_cat){
     
                $this->{$variable}[$dato_cat->key.""] = $dato_cat->name;
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

            "Conceptos" => "array|required",

            "Conceptos.*.ClaveProdServ" => "string|required",
            "Conceptos.*.NoIdentificacion" => "string",
            "Conceptos.*.Cantidad" => "integer|required",
            "Conceptos.*.ClaveUnidad" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_unidad_medida))
            ],
            "Conceptos.*.Unidad" => [
                "string",
                "required",
                "in:".implode(",",array_values($this->catalogo_unidad_medida))
            ],
            "Conceptos.*.ValorUnitario" => "numeric|required",
            "Conceptos.*.Descripcion" => "string|required",
            "Conceptos.*.Descuento" => "string",
            
            "Conceptos.*.Impuestos" => "array",
            
            "Conceptos.*.Impuestos.Traslados" => "array",
            "Conceptos.*.Impuestos.Traslados.*.Base" => "numeric|required",
            "Conceptos.*.Impuestos.Traslados.*.Impuesto" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_impuesto_traslados))
            ],
            "Conceptos.*.Impuestos.Traslados.*.TipoFactor" => [
                "string",
                "required",
                "in:".implode(",",$this->catalogo_tipo_factor)
            ],
            "Conceptos.*.Impuestos.Traslados.*.TasaOCuota" => "numeric|required",
            "Conceptos.*.Impuestos.Traslados.*.Importe" => "numeric|required|min:1",
            
            "Conceptos.*.Impuestos.Retenidos" => "array",
            "Conceptos.*.Impuestos.Retenidos.*.Base" => "numeric|required",
            "Conceptos.*.Impuestos.Retenidos.*.Impuesto" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_impuesto_retenciones))
            ],
            "Conceptos.*.Impuestos.Retenidos.*.TipoFactor" => [
                "string",
                "required",
                "in:".implode(",",$this->catalogo_tipo_factor)
            ],
            "Conceptos.*.Impuestos.Retenidos.*.TasaOCuota" => "numeric|required",
            "Conceptos.*.Impuestos.Retenidos.*.Importe" => "numeric|required|min:1",
            
            "Conceptos.*.Impuestos.Locales" => "array",
            "Conceptos.*.Impuestos.Locales.*.Impuesto" => [
                "string",
                "required",
                "in:".implode(",",$this->impuestos_locales)
            ],
            "Conceptos.*.Impuestos.Locales.*.TasaOCuota" => "numeric|required",

            "Conceptos.*.NumeroPedimento" => "string",
            "Conceptos.*.Predial" => "string",
            
            "Conceptos.*.Partes" => "array",

            "Conceptos.*.Partes.*.ClaveProdServ" => "string|required",
            "Conceptos.*.Partes.*.NoIdentificacion" => "string",
            "Conceptos.*.Partes.*.Cantidad" => "integer|required",
            "Conceptos.*.Partes.*.unidad" => [
                "string",
                "required",
                "in:".implode(",",array_values($this->catalogo_unidad_medida))
            ],
            "Conceptos.*.Partes.*.ValorUnitario" => "numeric|required",
            "Conceptos.*.Partes.*.Descripcion" => "string|required",
            
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
            "CfdiRelacionados.*.TipoRelacion" => [
                "string",
                "required",
                "in:".implode(",",array_keys($this->catalogo_tipo_relacion))
            ],
            "CfdiRelacionados.*.UUID" => "array|required",
            "CfdiRelacionados.*.UUID.*" => "string",
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

    }

    public function __construct(){

        $this->api_key = config('facturapuntocom.api_key','');
        $this->secret_key = config('facturapuntocom.secret_key','');
        
        $this->fplugin = config('facturapuntocom.fplugin','');
        $this->is_sandbox = config('facturapuntocom.is_sandbox',true);

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

        return $this->sendRequest('GET',"v1/series");           

    }

    public function createClient($data){

        list($resultado,$resultados_validacion) = $this->validateData($data,$this->rules_for_client);

        if($resultado){

            return $this->sendRequest('POST',"v1/clients/create",$resultados_validacion);

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

        if($resultado){

            return $this->sendRequest('POST',"v3/cfdi33/create",$resultados_validacion);

        }else{

            return $this->response(false,"Errores de validacion",$resultados_validacion);

        }

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

}