<?php

namespace Importaremx\Facturapuntocom\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Importaremx\Facturapuntocom\Models\CfdiBatch;

class processCfdiBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batch;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(CfdiBatch $batch)
    {
        $this->batch = $batch;        
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{

            $this->batch->createNextCfdi();

            if($this->batch->hasNextElement()){
            
                $this->batch->newDispatch();
            }

        }catch(\Exception $err){

            \Log::debug($err->getMessage());

            $this->newDispatch();
        }

    }

}