<?php

namespace Importaremx\Facturapuntocom\Traits;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Importaremx\Facturapuntocom\Models\Cfdi;
use Importaremx\Facturapuntocom\Facturapuntocom;
use Importaremx\Facturapuntocom\ClientResponse;

trait HasCfdi
{

    protected $default_data = [
        "TipoDocumento" => "factura",
        "Cantidad" => 1,
        "ClaveUnidad" => "E48",
        "Unidad" => "Unidad de servicio",
        "Impuesto" => "002", 
        "TipoFactor" => "Tasa", 
        "TasaOCuota" => "0.160000", 
        "Moneda" => "MXN",
        "Comentarios" => "",
        "EnviarCorreo" => true
    ];


	private $overridables = [
        "Receptor" => true,
        "ClaveProdServ" => true,
        "NoIdentificacion" => false,
        "ValorUnitario" => true,
        "Descripcion" => true,
        "UsoCFDI" => true,
        "FormaPago" => true,
        "MetodoPago" => true,
        "CondicionesDePago" => true,
        "Moneda" => false,
        "NumOrder" => false,
        "Fecha" => false,
        "Comentarios" => false,
        "EnviarCorreo" => false,
        "LugarExpedicion" => false
    ];

    protected $cfdi_mapping = [];

    protected $contiene_iva = false;

    public function createCfdi(){

        $data = $this->default_data;

        foreach($this->overridables as $overridable => $requerido){

            if(!empty($this->{$overridable})){

                $data[$overridable] = $this->{$overridable};
            }
        }

        if($this->cfdi()->count() > 0){
            return new ClientResponse(false,"Este objeto ya tiene creado un CFDI");
        }

        //Cargar y sobreescribir los datos en caso necesario si se define en el modelo hijo
        foreach($this->cfdi_mapping as $mapping_key => $mapping_attribute){
            $data[$mapping_key] = $mapping_attribute;
        }

        foreach($this->overridables as $overridable => $requerido){
            
            if($requerido && empty($data[$overridable])){
                return new ClientResponse(false,"El campo ".$overridable." es requerido");
            }
        }

        //Calcular los valores de precio unitario e IVA
        if($this->contiene_iva){

            $valor_unitario = $data["ValorUnitario"] / (1 + $data["TasaOCuota"]*1);
            $impuesto_iva = $valor_unitario * $data["TasaOCuota"];

        }else{

            $valor_unitario = $data["ValorUnitario"]*1;
            $impuesto_iva = $valor_unitario * $data["TasaOCuota"];

        }


        $data_cfdi = [
            "Receptor" => ["UID" => $data["Receptor"]],
            "TipoDocumento" => $data["TipoDocumento"],
            "UsoCFDI" => $data["UsoCFDI"],
            "Conceptos" => [
                [
                    'ClaveProdServ' => $data["ClaveProdServ"],
                    'Cantidad' => $data["Cantidad"],
                    'ClaveUnidad' => $data["ClaveUnidad"],
                    'Unidad' => $data["Unidad"],
                    'ValorUnitario' => $valor_unitario,
                    'Descripcion' => $data["Descripcion"],
                    'Impuestos' => [
                        'Traslados' => [
                            [
                                'Base' => $data["ValorUnitario"], 
                                'Impuesto' => $data["Impuesto"], 
                                'TipoFactor' => $data["TipoFactor"], 
                                'TasaOCuota' => $data["TasaOCuota"], 
                                'Importe' => $impuesto_iva,
                            ]
                        ]
                    ],
                ],
            ],
            "FormaPago" => $data["FormaPago"],
            "MetodoPago" => $data["MetodoPago"],
            "CondicionesDePago" => $data["CondicionesDePago"],
            "Moneda" => $data["Moneda"],
            "EnviarCorreo" => $data["EnviarCorreo"],
        ];

        if(!empty($data["Fecha"])){
            $data_cfdi["Fecha"] = $data["Fecha"];
        }

        if(!empty($data["Comentarios"])){
            $data_cfdi["Comentarios"] = $data["Comentarios"];
        }

        if(!empty($data["NumOrder"])){
            $data_cfdi["NumOrder"] = $data["NumOrder"];
        }

        if(!empty($data["LugarExpedicion"])){
            $data_cfdi["LugarExpedicion"] = $data["LugarExpedicion"];
        }

        $facturapuntocom_client = new Facturapuntocom();

        $result = $facturapuntocom_client->createCfdi($data_cfdi);

        if($result->status){
            Cfdi::create([
                "xml" => null,
                "pdf" => null,
                "json" => json_encode($result->data),
                "owner_id" => $this->id,
                "owner_type" => static::class
            ]);
        }

        return $result;

    }

    public function cfdi()
    {
        return $this->morphOne(Cfdi::class, 'owner');
    }

}