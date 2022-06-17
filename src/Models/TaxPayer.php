<?php

namespace Importaremx\Facturapuntocom\Models;

use Illuminate\Database\Eloquent\Model;

class TaxPayer extends Model
{

    protected $guarded = ['id'];

    protected $table = "importaremx_taxpayer";

    protected $fillable = ["uid_sandbox","uid","model_id","model_type"];


    public function model()
    {
        return $this->morphTo("model");
    }

    public function cfdis()
    {
        return $this->morphToMany(Cfdi::class, 'owner');
    }

}
