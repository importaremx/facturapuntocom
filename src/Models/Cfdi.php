<?php

namespace Importaremx\Facturapuntocom\Models;

use Illuminate\Database\Eloquent\Model;
use Importaremx\Facturapuntocom\Facturapuntocom;
use Importaremx\Facturapuntocom\ClientResponse;

class Cfdi extends Model
{

    /*
    Posibles valores para el campo status
    1 => Activo
    2 => Cancelación solicitada
    3 => No cancelable (Activo)
    4 => Cancelado correctamente
    */
    
    protected $guarded = ['id'];

    protected $table = "importaremx_cfdi";

    protected $fillable = ["xml","pdf","json","owner_id","owner_type","status"];

    public function getJsonAttribute(){
        return json_decode(json_decode($this->attributes["json"]));
    }

    public function owner()
    {
        return $this->morphTo("owner");
    }

    public function downloadPdf(){

        if(empty($this->pdf)){

            $facturapuntocom_client = new Facturapuntocom();

            $results = $facturapuntocom_client->downloadPdfCFDI($this->json->uid);

            if($results->status){
                $this->update(["pdf" => $results->message]);
            }

            return new ClientResponse(true,"Se ha descargado correctamente el PDF",["pdf" => $results->message]); 
 
        }else{
            return new ClientResponse(true,"Este CFDI ya tenía previamente su pdf descargado",["pdf" => $this->pdf]); 
        }

    }

    public function downloadXml(){

        if(empty($this->xml)){

            $facturapuntocom_client = new Facturapuntocom();

            $results = $facturapuntocom_client->downloadXmlCFDI($this->json->uid);

            if($results->status){
                $this->update(["xml" => $results->message]);
            }

            return new ClientResponse(true,"Se ha descargado correctamente el XML",["xml" => $results->message]); 
 
        }else{
            return new ClientResponse(true,"Este CFDI ya tenía previamente su xml descargado",["xml" => $this->xml]); 
        }

    }

    public function sendMail(){

        $facturapuntocom_client = new Facturapuntocom();

        return $facturapuntocom_client->sendMailCFDI($this->json->uid);

    }

    public function sendCancelRequest(){

        $facturapuntocom_client = new Facturapuntocom();

        if($this->status == 2){

            return new ClientResponse(true,"Ya se ha enviado previamente la solicitud de cancelacion de este CFDI al SAT");

        }else if($this->status == 3){

            return new ClientResponse(false,"El SAT ha determinado que este CFDI ya no puede ser cancelado");

        }else if($this->status == 4){

            return new ClientResponse(true,"Este CFDI ya ha sido cancelado correctamente ante el SAT");

        } else{

            $results = $facturapuntocom_client->sendCancelRequestCFDI($this->json->uid);
            
            if($results->status){

                $this->update(["status" => 2]);

            }

            return $results;

        }

    }

    public function checkCancelRequestStatus(){

        $facturapuntocom_client = new Facturapuntocom();

        if($this->status == 2){

            $results = $facturapuntocom_client->checkCancelRequestStatusCFDI($this->json->uid);

            if($results->status){
                switch($results->data->Estado){
                    case "Cancelado" :

                        $this->update(["status" => 4]);
                        break;

                    case "Vigente" :
                        if($results->data->EsCancelable == "No Cancelable"){
                            $this->update(["status" => 3]);
                        }
                        break;
                }

            }

            return new ClientResponse(true,"Se obtuvo correctamente la respuesta de cancelación del SAT",["cfdi_status" => $this->status]);

        }else{

            return new ClientResponse(true,"Ya se obtuvo previamente la respuesta de cancelación del SAT",["cfdi_status" => $this->status]);


        }

    }

}
