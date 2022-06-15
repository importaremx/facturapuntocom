<?php

namespace Importaremx\Facturapuntocom\Traits;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Importaremx\Facturapuntocom\Models\TaxPayer;
use Importaremx\Facturapuntocom\Models\Cfdi;
use Importaremx\Facturapuntocom\Facturapuntocom;
use Importaremx\Facturapuntocom\ClientResponse;

trait IsTaxPayer
{

    private $overridables = [
        "nombre",
        "apellidos",
        "email",
        "email2",
        "email3",
        "telefono",
        "razons",
        "rfc",
        "calle",
        "numero_exterior",
        "numero_interior",
        "codpos",
        "colonia",
        "estado",
        "ciudad",
    ];

    protected $rfc_field = "rfc";

    protected $taxpayer_mapping = [];
    

    protected $relatable_type = "";
    protected $relatable_column = "model_id";

    public function taxpayer()
    {
        return $this->morphOne(TaxPayer::class, 'model');
    }

    public function cfdis()
    {

        if(empty($this->relatable_type)){

            $this->relatable_type = static::class;

            return $this->hasMany(Cfdi::class,"owner_id")
                ->where(
                    "owner_type",
                    static::class
                );
        }

        return $this->hasManyThrough(
            Cfdi::class,
            $this->relatable_type,
            $this->relatable_column
            ,"owner_id")
            ->where(
                "owner_type",
                $this->relatable_type
            );
    }

    protected function dataMapping(){

    }

    public function usarURLSandbox()
    {
        $facturapuntocom_client = new Facturapuntocom();
        $this->url = $facturapuntocom_client->usarURLSandboxFacturacion();
    }

    public function usarURLEntorno()
    {
        $facturapuntocom_client = new Facturapuntocom();
        $this->url = $facturapuntocom_client->usarURLFacturacion();
    }


    public function createOrUpdateTaxPayer($sandbox){

        $this->dataMapping();
        
        $data = [];
        foreach($this->overridables as $overridable){

            if(!empty($this->{$overridable})){

                $data[$overridable] = $this->{$overridable};
            }
        }
        
        //Cargar y sobreescribir los datos en caso necesario si se define en el modelo hijo
        foreach($this->taxpayer_mapping as $mapping_key => $mapping_attribute){
            $data[$mapping_key] = $mapping_attribute;
        }

        $facturapuntocom_client = new Facturapuntocom();

        $rfc = $this->{$this->rfc_field} ?: $data[$this->rfc_field];

        if(empty($rfc)){

            return new ClientResponse(false,"El campo de RFC no está configurado correctamente o está vacío");
        }

        $clientes_existentes = $facturapuntocom_client->getClients($rfc);

        if(!empty($clientes_existentes->data)){
            
            $result = $facturapuntocom_client->updateClient($clientes_existentes->data->UID,$data);
        }else{
            $result = $facturapuntocom_client->createClient($data);
        }

        //if(empty($this->taxpayer)){
            //Crear el taxpayer para este modelo
            if($result->status){
                if ($sandbox) {
                    TaxPayer::updateOrCreate([
                        "model_id" => $this->id,
                        "model_type" => static::class
                    ],
                    [
                        "uid_sandbox" => $result->data->UID
                    ]);
                }
                else{
                    TaxPayer::updateOrCreate([
                        "model_id" => $this->id,
                        "model_type" => static::class
                    ],
                    [
                        "uid" => $result->data->UID
                    ]);
                }
            }
        //}
        
        return $result;
    }

}