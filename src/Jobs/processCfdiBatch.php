<?php

namespace Importaremx\Facturapuntocom\Jobs;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Bus\Queueable;

use Importaremx\Facturapuntocom\Models\CfdiBatch;

class processCfdiBatch implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels, Queueable;

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

            $this->batch->newDispatch();
        }

    }

}