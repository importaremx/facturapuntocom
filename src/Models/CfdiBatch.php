<?php

namespace Importaremx\Facturapuntocom\Models;

use Illuminate\Database\Eloquent\Model;
use Importaremx\Facturapuntocom\Facturapuntocom;
use Importaremx\Facturapuntocom\ClientResponse;
use Importaremx\Facturapuntocom\Traits\HasCfdi;

use Importaremx\Facturapuntocom\Jobs\processCfdiBatch;

class CfdiBatch extends Model
{
    
    protected $guarded = ['id'];

    protected $table = "importaremx_cfdi_batch";

    protected $fillable = ["elements","element_class","finished"];

    protected $casts = [
        "elements" => "json"
    ];

    private $next_element;
    protected $class_type;

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->class_type = app($this->element_class);
    }

    public static function boot() {

        parent::boot();

        static::creating(function($obj){

            //validar que se pueda crear una clase a partir del string
            $class= app($obj->element_class);

            if(!in_array(HasCfdi::class, class_uses($class))){
                throw new \Exception("La clase del batch debe usar el trait HasCfdi");
            }

            if(is_array($obj->elements)){

                $values_array = array_fill(0, count($obj->elements), ["id" => null,"results" => null]);

                foreach($values_array as $key => $values){
                    $values_array[$key]["id"] = $obj->elements[$key];
                }

                $obj->elements = $values_array;
            }

        });

        static::created(function($obj){

            $obj->newDispatch();
        
        });

        static::saved(function($obj){

            $obj->class_type = app($obj->element_class);

        });
    } 

    public function getNextElement(){

        foreach($this->elements as $key => $element){

            if(empty($element["results"])){

                $id = $element["id"];
            
                try{

                    $obj = $this->class_type->find($id);

                    if(!empty($obj)){

                        $this->next_element = $obj;
                        return $obj;
                    }else{

                        $this->saveResult($key,new ClientResponse(false,"No existe un elemento con este ID"));

                    }

                }catch(\Exception $ex){
                    return null;
                }

            }
        }

        return null;
    }

    public function hasNextElement(){
        $this->getNextElement();
        return (!empty($this->next_element));        
    }

    public function createNextCfdi(){

        $this->getNextElement();

        if(!empty($this->next_element)){

            $results = $this->next_element->createCfdi();

            foreach($this->elements as $key => $element){
                
                if($element["id"] == $this->next_element->id){
                    
                    $this->saveResult($key, $results);
                }
            }
            
        }else{

            \DB::table($this->getTable())
                ->where($this->getKeyName(),$this->{$this->getKeyName()})
                ->update(["finished" => true]);            
        }

    }

    public function saveResult($key,$results){

        $elements = $this->elements;

        $elements[$key]["results"] = $results;

        \DB::table($this->getTable())
            ->where($this->getKeyName(),$this->{$this->getKeyName()})
            ->update(["elements" => json_encode($elements)]);

        $this->refresh();
    
    }

    public function newDispatch(){

        
        $queue_connection = config('facturapuntocom.queue_connection','');
        $queue_name = config('facturapuntocom.queue_name','');

        if(!empty($queue_connection)){

            dispatch(new processCfdiBatch($this))
                ->onConnection($queue_connection)
                ->onQueue($queue_name);
        }else{
            dispatch(new processCfdiBatch($this));
        }
    }

    public function getStatsAttribute(){

        $results = [];

        $elements = collect($this->elements);

        $results["total_elements"] = $elements->count();
        $results["total_pending"] = $elements->filter(function($item){
            return empty($item["results"]);
        })->count();

        $results["total_processed"] = $elements->count() - $results["total_pending"];

        $results["percentage_pending"] = round($results["total_pending"] * 100 / $results["total_elements"]);

        $results["percentage_processed"] = round($results["total_processed"] * 100 / $results["total_elements"]);

        return $results;

    }

}
