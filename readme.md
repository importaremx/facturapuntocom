# Facturapuntocom

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

Api Client para la api de factura.com

## Instalación

Via Composer

``` bash
$ composer require importaremx/facturapuntocom
```

## Configuracion

En config/app.php agregar el service provider:

Importaremx\Facturapuntocom\FacturapuntocomServiceProvider::class,

Para modificar la configuracion por default del paquete corra en la consola de php el siguiente comando:

php artisan vendor:publish --provider='Importaremx\Facturapuntocom\FacturapuntocomServiceProvider'

Se publicarán los siguientes archivos:
XXXXXXXXXXX_create_cfdi_tables.php En la carpeta de migraciones
facturapuntocom.php en la carpeta 'config'

###Variables de configuración

El archivo facturapuntocom.php contiene las siguientes variables:

'api_key', valor por default '', esta variable debe contener el api key de la cuenta de factura.com, se debe agregar la variable FACTURACOM_API_KEY en el archivo .env

'secret_key', valor por default '', esta variable debe contener el secret key de la cuenta de factura.com, se debe agregar la variable FACTURACOM_SECRET_KEY en el archivo .env
        
'fplugin', valor por default '9d4095c8f7ed5785cb14c0e3b033eeb8252416ed', esta variable es una constante determinada por factura.com, en caso de que en el futuro ese valor cambie, se puede modificar usando la variable FACTURACOM_FPLUGIN en el archivo env


'is_sandbox', valor por default true, Esta variable indica si el paquete funcionará o no en el sandbox de pruebas de factura.com, se debe usar la variable FACTURACOM_SANDBOX en el archivo .env

'serie_default', valor por default 1, Esta variable indica en que serie se estarán creando los CFDI's que genera el paquete, este valor debe ser obtenido desde la cuenta de factura.com, se debe usar la variable FACTURACOM_SERIE_DEFAULT en el archivo .env


'path_pdf', valor por default "cfdi_files/pdf", esta variable indica la ruta de almacenamiento dentro de la carpeta storage donde se guardarán los archivos pdf de los CFDIs, se debe usar la variable FACTURACOM_PATH_PDF en el archivo .env

'path_xml', valor por default "cfdi_files/xml", esta variable indica la ruta de almacenamiento dentro de la carpeta storage donde se guardarán los archivos xml de los CFDIs, se debe usar la variable FACTURACOM_PATH_XML en el archivo .env

'queue_connection' variable que define la conexión al controlador de colas para la facturacion por lotes, si no se ocupará ningun driver diferente a la configuracion default de laravel esta variable se deja vacía

'queue_name' variable que define el nombre de la cola en donde se publicarán los trabajos para la facturacion por lotes, si no se ocupará ningun driver diferente a la configuracion default de laravel esta variable se deja vacía
###Tablas en la DB

para correr la migracion del paquete una vez instalado se debe ejecutar el siguiente comando.

php artisan migrate 

(Se puede especificar el path del archivo publicado por el paquete para migrar solo las tablas de este paquete si fuera necesario)


## Uso

	Este paquete provee dos traits y el helper completo de factura.com

### Traits

#### IsTaxPayer

Este trait proporciona metodos para el modelo que funcione como contribuyente dentro del sistema. 

Se deben agregar la siguiente linea al modelo deseado:

use \Importaremx\Facturapuntocom\Traits\isTaxPayer;

Para modificar los valores por default del trait se puede agregar en el modelo la siguiente función:

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        //AQUI AGREGAR LOS VALORES MODIFICADOS DEL TRAIT
        //POR EJEMPLO:

        /*La variable $rfc_field indica qué atributo del modelo será tomado como RFC del contribuyente, por default tiene el valor 'rfc', pero lo puede modificar como se muestra en la siguiente linea*/
        
        $this->rfc_field = "rfc";

        /*Para hacer uso de la relacion "cfdis", que obtiene todos los CFDIS generados para este usuario, se debe configurar la relacion polimorfica,
        ejemplo*/

        /*Un User tiene una relacion 1 a muchos con el modelo Abonos, y cada abono a su vez tiene un cfdi. para indicar esta relacion se haría como se muestra en las siguientes lineas:*/

        /*(Esta variable indica el modelo de donde se obtendran los CFDI, es necesario que este modelo use el trait HasCfdi que se explicará mas adelante)*/
         $this->relatable_type = "App\Models\Abono";

         /*(Esta variable se refiere a la columna pivote de la tabla abono)*/
         $this->relatable_column = "user_id";
    }

Para mapear los datos del modelo hacia factura.com, se usa la siguiente funcion en el modelo:

    protected function dataMapping(){
        
         /*Mapeo de datos del modelo
         El siguiente array contendrá un array clave valor con los atributos necesarios para crear un contribuyente en factura.com seguido del valor, funcion anonima, o atributo de donde el modelo proveerá dicha información*/

        $this->taxpayer_mapping = [
            "nombre" => $this->name,
            "apellidos" => "apellidos",
            "email" => "otrocorreo@servidor.com",
            "email2" => function(){
                return "funcion@anonima.com";
                },
            "email3" => "otroemail3@email.com",
            "telefono" => "9612547499",
            "razons" => "Desarrollador ImportareMX TESTER",
            "rfc" => "siag880723hi9",
            "calle" => "Diagonal peje de oro",
            "numero_exterior" => 5,
            "numero_interior" => "",
            "codpos" => 29230,
            "colonia" => "Cuxtitali",
            "estado" => "Chiapas",
            "ciudad" => "San Cristóbal de las Casas"
        ];

    }

El trait provee el siguiente método para el modelo:

createOrUpdateTaxPayer : Crea en factura.com el contribuyente y lo asigna a este modelo, una vez configurado el trait basta con llamarlo de la siguiente manera:

$model = \App\Model\SomeModel::first();
$model->createOrUpdateTaxPayer();


Relacion cfdis() : Contiene todos los cfdis relacionados a este modelo, es necesario configurar la relacion polimórfica mencionada arriba.

Relacion taxpayer() : Contiene los datos del contribuyente del SAT relacionado al modelo.

#### HasCfdi

Este trait permite agregar a un modelo los metodos necesarios para crear un cfdi relacionado al modelo.

Se deben agregar la siguiente linea al modelo deseado:

use \Importaremx\Facturapuntocom\Traits\HasCfdi;

Para modificar los valores por default del trait se puede agregar en el modelo la siguiente función:

    public function dataMapping(array $attributes = array())
    {
         /*Mapeo de datos del modelo
         El siguiente array contendrá un array clave valor con los atributos necesarios para crear un cfdi en factura.com seguido del valor, funcion anonima, o atributo de donde el modelo proveerá dicha información*/

    	$this->cfdi_mapping = [

    		//El uid del receptor debe ser un valor agregado en la tabla de taxpayer creado por el paquete
    		"Receptor" => "60c127b3ec916",
    		"ClaveProdServ" => "01010101",
    		"ValorUnitario" => 100,
    		"Descripcion" => "Fctura de prueba, descirpción de prueba",
    		"UsoCFDI" => "G03",
    		"FormaPago" => "01",
    		"MetodoPago" => "PUE",
    		"CondicionesDePago" => "Condiciones de pago de pruebas",
    	];

    }


El trait provee el siguiente método para el modelo:

createCfdi() 

Despues de configurar el trait como se menciona arriba, basta con ejecutar el metodo createCfdi() en el modelo para crear y guardar el cfdi en la base de datos.


El modelo Importaremx\Facturapuntocom\Models\Cfdi contiene los metodos necesarios para la administracion del cfdi:

downloadPdf = Descarga el pdf, lo almacena en la carpeta del storage y guarda la ubicacion en la base de datos

downloadXml = Descarga el xml, lo almacena en la carpeta del storage y guarda la ubicacion en la base de datos

sendMail = Envia mail a los correos registrados del contribuyente

sendCancelRequest = solicita la cancelacion del CFDI, en algunas ocasiones la respuesta es automatica, pero es recomendable usar el metodo siguiente para revisar el status del cfdi ante el sat

checkCancelRequestStatus = Revisa el estatus de la cancelacion del CFDI


#### Creacion de cfdis por lotes

El paquete ofrece un modelo para facturar por lotes:

\Importaremx\Facturapuntocom\Models\CfdiBatch

Espera los siguientes parametros:

elements: es un array de enteros, Id's de los elementos a generar cfdi
element_class: es la clase del modelo en la que se generarán los cfdi (Es obligatorio que la clase use el Trait hasCfdi del paquete, para que se puedan generar correctamente los cfdi)

El modelo contiene un atributo llamado stats que contiene información del procesamiento de datos:

total_elements : El total de cfdis en el batch
total_pending : Total de cfdis pendientes de generar
total_processed : Total de cfdis procesados
percentage_pending : Porcentaje de cfdis pendientes
percentage_processed : Porcentaje de cfdis procesados

El modelo contiene un atributo llamado finished que indica si se ha completado o no el procesamiento del batch

Nota: El procesamiento del batch funciona con Jobs y hereda la configuracion de colas que tenga el proyecto, adicionalmente se pueden configurar en el archivo config/facturapuntocom.php las variables queue_connnection y queue_name para indicar la configuracion o driver de colas que usará el cfdibatch

