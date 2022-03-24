<?php
/**
 * Importador de productos desde catálogo de proveedor
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_'))
    exit;

class AdminCrearProductosController extends ModuleAdminController {
    
    public function __construct() {
        require_once (dirname(__FILE__) .'/../../importaproveedor.php');

        $this->lang = false;
        $this->bootstrap = true;        
        $this->context = Context::getContext();
        
        parent::__construct();
        
    }
    
    /**
     * AdminController::init() override
     * @see AdminController::init()
     */
    public function init() {
        $this->display = 'add';
        parent::init();
    }
   
    /*
     *
     */
    public function setMedia(){
        parent::setMedia();
        $this->addJs($this->module->getPathUri().'views/js/back_crea_producto.js');
        //añadimos la dirección para el css
        //$this->addCss($this->module->getPathUri().'views/css/back.css');
    }


    /**
     * AdminController::renderForm() override
     * @see AdminController::renderForm()
     */
    public function renderForm() {       
        //generamos el token de AdminImportaProveedor ya que lo vamos a usar para llamar con ajax a la función comprobarReferencia que está en ese controlador
        $token_admin_importa_proveedor = Tools::getAdminTokenLite('AdminImportaProveedor');  
                
        //sacamos todos los suppliers
        $suppliers = Supplier::getSuppliers();

        //introducir un campo sin proveedor al Select de proveedores, se trata de un array que contiene el array a añadir (merge)
        $proveedor_vacio = array(array('id_supplier'=> 0, 'name'=> 'Selecciona proveedor'));
        $suppliers = array_merge($proveedor_vacio, $suppliers);

        //sacamos todos los fabricantes
        $manufacturers = Manufacturer::getManufacturers();

        //introducir un campo al Select de fabricantes, que será el de otros fabricantes para que salga por defecto.
        $otros_fabricantes = array(array('id_manufacturer'=> 34, 'name'=> 'Otros Fabricantes'));
        $manufacturers = array_merge($otros_fabricantes, $manufacturers);

        //sacamos los grupos de impuestos
        $taxes = TaxRulesGroup::getTaxRulesGroups();
        //ponemos por defecto ES standar 21%
        $es_21 = array(array('id_tax_rules_group'=> 41, 'name'=> 'ES Standard rate (21%)'));
        $taxes = array_merge($es_21, $taxes);

        //sacamos features de Tipo de producto id 8 (getFeature($id_lang, $id_feature)) ordenando la sql por value pero que saque primero 'Otros' id 155
        $sql_features = 'SELECT fvl.id_feature_value AS id_feature_value, fvl.value AS name
        FROM lafrips_feature_value fev
        LEFT JOIN lafrips_feature_value_lang fvl ON fvl.id_feature_value = fev.id_feature_value 
            AND fvl.id_lang = 1
        WHERE fev.id_feature = 8
        ORDER BY FIELD (fvl.id_feature_value, 155) DESC, fvl.value';
        
        $features = Db::getInstance()->ExecuteS($sql_features);

        //sacamos los grupos de atributos
        $attribute_group = AttributeGroup::getAttributesGroups(1);
        //introducir un campo sin grupo de atributos al Select de grupos de atributos
        $grupo_vacio = array(array('id_attribute_group'=> 0, 'name'=> 'Selecciona Tipo'));
        $attribute_group = array_merge($grupo_vacio, $attribute_group);

        //sacamos los atributos por grupo
        // $atributos = Attribute::getAttributes(1);
        // //introducir un campo sin atributos al Select de atributos
        // $atributo_vacio = array(array('id_attribute'=> 0, 'name'=> 'Selecciona Atributo'));
        // $atributos = array_merge($atributo_vacio, $atributos);


        //preparar array para el número de combinaciones del producto, si las tiene, lo preparamos con un select hasta 10
        $num_combination = array(
            array( 'number'=> 0, 'numero' => 'Nº de combinaciones'),
            array( 'number'=> 1, 'numero' => 'Una'),
            array( 'number'=> 2, 'numero' => 'Dos'),
            array( 'number'=> 3, 'numero' => 'Tres'),
            array( 'number'=> 4, 'numero' => 'Cuatro'),
            array( 'number'=> 5, 'numero' => 'Cinco'),
            array( 'number'=> 6, 'numero' => 'Seis'),
            array( 'number'=> 7, 'numero' => 'Siete'),
            array( 'number'=> 8, 'numero' => 'Ocho'),
            array( 'number'=> 9, 'numero' => 'Nueve'),
            array( 'number'=> 10, 'numero' => 'Diez'),
        );

        $this->fields_form = array(
            'legend' => array(
                'title' => 'creador flash de productos',
                'icon' => 'icon-pencil'
            ),
            'input' => array(              
                // 20/10/2021 Añadimos un check y un input para permitir buscar un producto "base" del cual sacaremos datos para rellenar algunas características, principalmente las categorías que tenga marcadas, la descripción y el tipo de producto, de modo que se ahorre tiempo
                //checkbox para indicar que queremos usar un producto como "molde"
                array(
                    'type' => 'checkbox',
                    'label' => 'Importar características',
                    'name' => 'check_importar',                      
                    'id' => 'check_importar',                     
                    'hint' => 'Marcar si quieres importar las categorías y descripción de otro producto ya existente.',
                    'values' => array(	                        
                        'query' => array(
                            array(
                           'id' => 'si'
                           ),
                        ),	
                        'id' => 'id'                                                                  
                    ),
                    
                ),                 
                //input para introducir la referencia del producto del que copiar categorías etc
                array(
                    'type' => 'text',
                    //'label' => 'Imagen Principal',
                    'name' => 'producto_importar',
                    'required' => false,                     
                    // 'hint' => 'Introduce la referencia completa',                    
                ),    
                //input para introducir nombre para el producto
                array(
                    'type' => 'text',
                    'label' => 'Nombre de producto',
                    'name' => 'product_name',
                    'required' => true, 
                    'hint' => 'Introduce el nombre para asignar al producto',                    
                ),  
                //input para introducir la referencia de producto
                array(
                    'type' => 'text',
                    'label' => 'Referencia',
                    'name' => 'product_reference',
                    'required' => true, 
                    'hint' => 'Introduce la referencia para asignar al producto',
                ),  
                //input para asignar ean 
                array(
                    'type' => 'text',
                    'label' => 'Ean-13',
                    'name' => 'ean',
                    'class' => 'input_ean',
                    'required' => false, 
                    'hint' => 'Introduce el ean del producto',
                ),  
                //textarea para descripción corta
                // array(
                //     'type' => 'textarea',
                //     'label' => 'Descripción Corta',
                //     'name' => 'short_description',
                //     'required' => false, 
                //     'hint' => 'Introduce la descripción del producto',
                //     ),      
                //textarea para descripción larga
                array(
                    'type' => 'textarea',
                    'label' => 'Descripción',
                    'name' => 'long_description',
                    'required' => false, 
                    'hint' => 'Introduce la descripción del producto',                    
                ),  
                //Select con las features de id 8, Tipo de producto
                array(
                    'type' => 'select',
                    'label' => 'Tipo de producto',
                    'name' => 'id_feature_value',
                    'required' => true,
                    'options' => array(
                        'query' => $features,
                        'id' => 'id_feature_value',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona el tipo de producto',
                ),     
                //input para introducir metatítulo, metemos default con javascript
                array(
                    'type' => 'text',
                    'label' => 'Meta-título',
                    'name' => 'meta_title',
                    'required' => false, 
                    'hint' => 'Introduce el meta-título del producto',
                ),  
                //input para introducir metadescripción, metemos default con javascript
                array(
                    'type' => 'textarea',
                    'label' => 'Meta-descripción',
                    'name' => 'meta_description',
                    'required' => false, 
                    'hint' => 'Introduce la meta descripción del producto',
                ),  
                //Select con los fabricantes disponibles
                array(
                    'type' => 'select',
                    'label' => 'Fabricante',
                    'name' => 'id_manufacturer',
                    'required' => true,
                    'options' => array(
                        'query' => $manufacturers,
                        'id' => 'id_manufacturer',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona el fabricante del producto',
                ),     
                //Select con los proveedores disponibles
                array(
                    'type' => 'select',
                    'label' => 'Proveedor',
                    'name' => 'id_supplier',
                    'required' => true,
                    'options' => array(
                        'query' => $suppliers,
                        'id' => 'id_supplier',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona el proveedor del producto',
                ),
                //input para introducir la referencia de proveedor si no tiene combinaciones
                array(
                    'type' => 'text',
                    'label' => 'Referencia Proveedor',
                    'name' => 'product_supplier_reference',
                    'required' => false, 
                    'hint' => 'Introduce la referencia de proveedor del producto',                    
                ),  
                array(  
                    'type' => 'text',                  
                    //'cast' => 'floatval',
                    'label' => 'Precio de coste',
                    'name' => 'wholesale_price',
                    'required' => false, 
                    'hint' => 'Introduce el precio de proveedor, decimal con punto',
                    //'validation' => 'isUnsignedFloat',
                ),
                array(  
                    'type' => 'text',                  
                    //'cast' => 'floatval',
                    'label' => 'Precio de venta',
                    'name' => 'sell_price',
                    'required' => false, 
                    'hint' => 'Introduce el precio de venta con IVA, decimal con punto',
                    //'validation' => 'isUnsignedFloat',
                ),    
                //Select con los grupos de impuestos
                array(
                    'type' => 'select',
                    'label' => 'Regla de impuestos',
                    'name' => 'id_tax_rules_group',
                    'required' => true,
                    'options' => array(
                        'query' => $taxes,
                        'id' => 'id_tax_rules_group',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona la regla de impuestos para el producto',
                ),  
                //input para el peso, le meteremos 1.111 por defecto mediante javascript   
                array(  
                    'type' => 'text',                  
                    'cast' => 'floatval',
                    'label' => 'Peso',
                    'name' => 'weight',
                    'required' => false, 
                    'hint' => 'Introduce el peso del producto',                    
                ),                  
                //checkbox para indicar que el producto tiene combinaciones
                array(
                    'type' => 'checkbox',
                    'label' => 'Combinaciones',
                    'name' => 'producto_combinaciones',                      
                    'id' => 'producto_combinaciones',                     
                    'hint' => 'Marcar si el producto tiene combinaciones',
                    'values' => array(	                        
                        'query' => array(
                            array(
                           'id' => 'si'
                           ),
                        ),	
                        'id' => 'id'                                                                  
                    ),
                    
                ), 
                //Select con los grupos de atributos disponibles
                array(
                    'type' => 'select',
                    //'label' => 'Grupo de Atributos',
                    'name' => 'attribute_group',
                    'required' => false,
                    'options' => array(
                        'query' => $attribute_group,
                        'id' => 'id_attribute_group',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona el grupo de atributos para las combinaciones',
                ),
                //select con el nº de combinaciones que se asignarán al producto 
                array(
                    'type' => 'select',
                    //'label' => 'Nº Combinaciones',
                    'name' => 'num_combination',
                    'required' => false,
                    'options' => array(
                        'query' => $num_combination,
                        'id' => 'number',
                        'name' => 'numero'
                    ),
                    'hint' => 'Selecciona el número de combinaciones que tendrá el producto',
                ), 
                //checkbox para indicar que el producto tiene imagenes
                array(
                    'type' => 'checkbox',
                    'label' => 'Imágenes',
                    'name' => 'producto_imagenes',                      
                    'id' => 'producto_imagenes',                     
                    'hint' => 'Marcar si vas a asignar imágenes al producto. Hasta un máximo de 6',
                    'values' => array(	                        
                        'query' => array(
                            array(
                           'id' => 'si'
                           ),
                        ),	
                        'id' => 'id'                                                                  
                    ),
                    
                ), 
                //los inputs para las imágenes se ocultarán con JQuery al cargar, y se mostrarán si se marca el checkbox de imágenes, ponemos 6
                //input para introducir la url de la imagen cover
                array(
                    'type' => 'text',
                    //'label' => 'Imagen Principal',
                    'name' => 'cover_image_1',
                    'required' => false, 
                    'class' => 'imagenes',
                    'hint' => 'Introduce la url para la imagen principal',                    
                ),  
                // 5 inputs para introducir la url de otras imágenes
                array(
                    'type' => 'text',
                    //'label' => 'Otra imagen',
                    'name' => 'image_2',
                    'required' => false, 
                    'class' => 'imagenes',
                    'hint' => 'Introduce la url para imagen secundaria',                    
                ),  
                array(
                    'type' => 'text',
                    //'label' => 'Otra imagen',
                    'name' => 'image_3',
                    'required' => false, 
                    'class' => 'imagenes',
                    'hint' => 'Introduce la url para imagen secundaria',                    
                ),  
                array(
                    'type' => 'text',
                    //'label' => 'Otra imagen',
                    'name' => 'image_4',
                    'required' => false, 
                    'class' => 'imagenes',
                    'hint' => 'Introduce la url para imagen secundaria',                    
                ),  
                array(
                    'type' => 'text',
                    //'label' => 'Otra imagen',
                    'name' => 'image_5',
                    'required' => false, 
                    'class' => 'imagenes',
                    'hint' => 'Introduce la url para imagen secundaria',                    
                ),  
                array(
                    'type' => 'text',
                    //'label' => 'Otra imagen',
                    'name' => 'image_6',
                    'required' => false, 
                    'class' => 'imagenes',
                    'hint' => 'Introduce la url para imagen secundaria',                    
                ),   
                //input hidden con el token para usarlo por ajax
                array(  
                    'type' => 'hidden',                    
                    'name' => 'token_admin_importa_proveedor_'.$token_admin_importa_proveedor,
                    'id' => 'token_admin_importa_proveedor_'.$token_admin_importa_proveedor,
                    'required' => false,                                        
                ),                
                       
            ),
            'reset' => array('title' => 'Limpiar', 'icon' => 'process-icon-eraser icon-eraser'),   
            'submit' => array('title' => 'Guardar', 'icon' => 'process-icon-save icon-save'),            
        );
        
        $this->displayInformation(
                'Introduce los valores para crear un nuevo producto<br>El producto se asignará automaticamente a la categoría por defecto Creador Flash, Otros Fabricantes<br>
                Tendrá asignado el almacén Online y la gestión avanzada'
        );
        return parent::renderForm();
    }

    /*
    * 20/10/2021 Función que busca en la BD si la referencia introducida para copiar datos de un producto existe y si es así devuelve sus categorías y nombre/descripción, seo y tipo
    *
    */
    public function ajaxProcessComprobarReferenciaImportar(){        
        //asignamos a $referencia el valor introducido por el usuario     
        $referencia = Tools::getValue('referencia_importar',0);
        if(!$referencia)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se encuentra el producto.')));

        $response = true;
        //Buscamos la referencia en lafrips_product
        $sql_existe_referencia = 'SELECT id_product FROM lafrips_product WHERE reference = "'.$referencia.'";';

        $existe_referencia = Db::getInstance()->executeS($sql_existe_referencia);

        if(count($existe_referencia) > 1) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Referencia duplicada')));
        } else if(!$existe_referencia) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'La referencia no existe')));
        } else {
            //la referencia del producto existe, obtenemos sus categorías asignadas y su descripción corta
            $id_product = $existe_referencia[0]['id_product'];

            $product = new Product($id_product);
            $id_category_default = $product->id_category_default;
            $sql_category_default = 'SELECT id_category, name FROM lafrips_category_lang WHERE id_lang = 1 AND id_category = '.$id_category_default;
            $category_default = Db::getInstance()->executeS($sql_category_default)[0];

            //obtenemos las categorías por id , para almacenarlas en un input hidden en el front con javascript, y también sacamos los nombres para que el usuario vea que categorías son. Para evitar categorías como amazon, disponible recatalogar, etc, hacemos una select utilizando nleft nright, incluyendo solo lo de dentro de La Frikileriakids 2232 y Para mayores 2555 (2551 test), luego añadiremos Creador flash por defecto?. Quitamso la categoría de precio, que la calcularemos al crear el producto según su precio.
            //para sacar el intervalo en función del id de frikileria kids y adultos, evitando que cambie al añadir nuevas categorías, usamos la función de clase getInterval()
            $intervalo_kids = Category::getInterval(2232);
            $nleft_kids = $intervalo_kids['nleft'];
            $nright_kids = $intervalo_kids['nright'];

            $intervalo_adulto = Category::getInterval(2555); #test 2551, prod 2555 27/10/2021
            $nleft_adulto = $intervalo_adulto['nleft'];
            $nright_adulto = $intervalo_adulto['nright'];

            $intervalo_precio = Category::getInterval(3);
            $nleft_precio = $intervalo_precio['nleft'];
            $nright_precio = $intervalo_precio['nright'];

            // $ids_categorias = Product::getProductCategories($id_product);
            $sql_categorias = 'SELECT cla.id_category, cla.name 
            FROM lafrips_category cat
            JOIN lafrips_category_lang cla ON cla.id_category = cat.id_category AND cla.id_lang = 1 
            JOIN lafrips_category_product cap ON cap.id_category = cat.id_category
            WHERE cap.id_product = '.$id_product.'
            AND
            ((cat.nleft > '.$nleft_kids.' AND cat.nright < '.$nright_kids.')
            OR 
            (cat.nleft > '.$nleft_adulto.' AND cat.nright < '.$nright_adulto.'))
            AND NOT (cat.nleft > '.$nleft_precio.' AND cat.nright < '.$nright_precio.')';
            
            $categorias = Db::getInstance()->executeS($sql_categorias);

            //de la consulta obtenemos por un lado los ids de las categorias para enviarlos al front "limpios" y meterlos en un input hidden
            $ids_categorias = array();
            foreach ($categorias AS $categoria) {
                array_push($ids_categorias, $categoria['id_category']);
            }

            //la descripción, nombre y seo solo en español
            $sql_descripciones = 'SELECT name, description_short, meta_description, meta_title  FROM lafrips_product_lang WHERE id_lang = 1 AND id_product = '.$id_product;
            $descripciones = Db::getInstance()->executeS($sql_descripciones)[0];
            //tipo de producto 
            $sql_feature_value = 'SELECT id_feature_value FROM lafrips_feature_product WHERE id_feature = 8 AND id_product = '.$id_product;
            $feature_value = Db::getInstance()->executeS($sql_feature_value)[0];
            
            die(Tools::jsonEncode(array(
                'message' => 'La referencia es correcta', 
                'category_default' => $category_default, 
                'ids_categorias' => $ids_categorias, 
                'categorias' => $categorias, 
                'descripciones' => $descripciones, 
                'feature_value' => $feature_value
            )));
        }
            
    }


    /*
    * Función que busca los atributos correspondientes a un grupo de atributos. Este grupo se selecciona en CrearProductos, y por ajax pedimos los atributos que corresponden
    *
    */
    public function ajaxProcessAtributosGrupo(){        
        //asignamos a $id_attribute_group el id del select seleccionado por el usuario al escoger grupo de atributos  
        $id_attribute_group = Tools::getValue('id_attribute_group',0);
        if(!$id_attribute_group)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No recibo el grupo de atributos.')));

        $response = true;
        //Buscamos los id_attribute y name correspondientes a ese grupo de atributos
        $sql_atributos = 'SELECT atl.id_attribute AS id_attribute, atl.name AS name
        FROM lafrips_attribute att
        JOIN lafrips_attribute_lang atl ON att.id_attribute = atl.id_attribute
        WHERE atl.id_lang = 1
        AND att.id_attribute_group = '.$id_attribute_group;

        $atributos = Db::getInstance()->executeS($sql_atributos);


        if(!$atributos){
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error con la selección de grupo de atributos')));
        }


        $lista_atributos = array();
        foreach ($atributos AS $atributo){
            $lista_atributos[$atributo['id_attribute']] = $atributo['name'];
        }
        //si se reciben los atributos, los enviamos de vuelta en la variable lista_atributos
        if($response)
            die(Tools::jsonEncode(array('message'=>'bien por ahora', 'lista_atributos' => $lista_atributos)));
    }



    public function postProcess() {

        parent::postProcess();

        //var_dump($_POST);
        if (((bool)Tools::isSubmit('submitAddconfiguration')) == true) {
            //var_dump($_POST);
            // var_dump(Tools::getValue('hidden_attribute_1', false));
            
            $nombre_producto = trim(pSQL(Tools::getValue('product_name', false)));
            $referencia_producto = trim(pSQL(Tools::getValue('product_reference', false)));
            $referencia_proveedor = trim(pSQL(Tools::getValue('product_supplier_reference', false)));
            $ean = trim(Tools::getValue('ean', false));
            //comprobamos que el aen sea un número de hasta 13 cifras
            if (!preg_match("/^[0-9]{1,13}$/", $ean)){
                $ean = '';
            }
            $descripcion = trim(pSQL(Tools::getValue('long_description', false)));
            $feature_value_id = (int) Tools::getValue('id_feature_value', false);
            $meta_titulo = trim(pSQL(Tools::getValue('meta_title', false)));
            $meta_descripcion = trim(pSQL(Tools::getValue('meta_description', false)));  
            $id_manufacturer = (int) Tools::getValue('id_manufacturer', false);
            $id_supplier = (int) Tools::getValue('id_supplier', false);
            $coste = (float) Tools::getValue('wholesale_price', false);
            $coste = round($coste , 2);
            $pvp = (float) Tools::getValue('sell_price', false);
            //$pvp = round($pvp , 2);
            $taxes = (int) Tools::getValue('id_tax_rules_group', false);
            //sabiendo la regla de impuestos y el pvp al que lo quiere el usuario, calculamos el precio sin iva, que se introducirá para crear el producto. Para buscar en la tabla tax haciendo join con tax_rule, pensaremos que id_country es 6 España y id_state = 0
            $sql_tax = 'SELECT tax.rate 
            FROM lafrips_tax tax
            JOIN lafrips_tax_rule tru ON tax.id_tax = tru.id_tax
            WHERE tru.id_country = 6 
            AND tru.id_state = 0
            AND tru.id_tax_rules_group = '.$taxes;
            $tax = (float) Db::getInstance()->getValue($sql_tax);
            $pvp_sin_IVA = round($pvp/(1 + (abs($tax) / 100)),4); //hay que redondear, no debe tener más de 6? decimales
                      
            $peso = (float) Tools::getValue('weight', false);

            //para log al final
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname;
            
            $sql_nombre_proveedor = 'SELECT name FROM lafrips_supplier WHERE id_supplier = '.$id_supplier;
            $nombre_proveedor = Db::getInstance()->getValue($sql_nombre_proveedor, $use_cache = true);

            // if (!$nombre_producto || $nombre_producto == ''){
            //     $this->errors[] = Tools::displayError('Debes introducir un nombre para el producto');
            //     //return parent::postProcess();
            // }

            //si se ha marcado que tenga combinaciones, sacamos el nº de ellas y por cada una sacamos el atributo asignado, ean si lo hay, referencia de producto y referencia de proveedor, metiendo todo en un array            
            if (Tools::getValue('producto_combinaciones_si') == 'on') {
                $combinaciones = array();
                $numero_combinaciones = (int) Tools::getValue('num_combination', false);
                for ($x = 1; $x <= $numero_combinaciones; $x++) {
                    //el id de atributo se saca del input hidden donde lo hemos almacenado con JQuery, ya que al ser un select generado dinámicamente, no podemos obtener su value con Tools::getValue()
                    $combinaciones[$x]['id_attribute'] = (int) Tools::getValue('hidden_attribute_'.$x, false);
                    $combinaciones[$x]['combination_reference'] = Tools::getValue('combination_reference_'.$x, false);
                    $combinaciones[$x]['combination_ean'] = Tools::getValue('combination_ean_'.$x, false);
                    $combinaciones[$x]['combination_supplier_reference'] = Tools::getValue('combination_supplier_reference_'.$x, false);
                } 

            }
            

            //creamos link rewrite desde el name
            $linkrewrite = Tools::link_rewrite($nombre_producto);              
              
            $disponible_now = 'Producto en Stock. Disponible para envíos urgentes 24h.'; 
            $available_now = 'Product in Stock. Available for emergency shipments 24 hours a day.';  
            //$available_later = 'Atención: el plazo de envío de este artículo es de una semana a diez días.';
            $default_lang = Configuration::get('PS_LANG_DEFAULT');

            //Creamos producto
            $product = new Product();
            $product->name = $this->generaIdiomas($nombre_producto);
            $product->link_rewrite = $this->generaIdiomas($linkrewrite);
            $product->meta_title = $this->generaIdiomas($meta_titulo);
            $product->meta_description = $this->generaIdiomas($meta_descripcion);
            $product->available_now = $this->generaIdiomas($disponible_now, $available_now);
            
            //para el importador solo queremos poner el español, ya que tendrán que limpiar la descripción, y desde 03/11/2021 lo metemos en la descripción corta para que no se dejen datos abajo. 
            // $product->description = $this->generaIdiomas($descripcion);  
            $product->description_short = array( 1 => $descripcion);  

            $product->price = (float) $pvp_sin_IVA;
            $product->wholesale_price = $coste;

            //si no se copian categorías de otro producto se le pone solo Creador Flash, si no se ponen las que tenía el producto escogido, asegurándose de que tenga una por defecto, si no Creador Flash. la categoría de precio la calcularemos al crear el producto según su precio
            if (Tools::getValue('check_importar_si') == 'on') {
                $id_category_default = (int) Tools::getValue('id_category_default', false);
                //comprobamos que llegue una categoría y que esta sea hija de Frikileria kids o Adultos, si no, ponemos creador flash. Tampoco vale que sea Adultos o frikileria kids, tiene que ser hija y no de precio. Para ello buscamos el id de la categoría buscando solo entre las dichas.
                if (!$id_category_default) {
                    $id_category_default = 2501;
                } else {
                    //comprobamos su posición
                    $intervalo_kids = Category::getInterval(2232);
                    $nleft_kids = $intervalo_kids['nleft'];
                    $nright_kids = $intervalo_kids['nright'];

                    $intervalo_adulto = Category::getInterval(2555); #test 2551, prod 2555 27/10/2021
                    $nleft_adulto = $intervalo_adulto['nleft'];
                    $nright_adulto = $intervalo_adulto['nright'];

                    $intervalo_precio = Category::getInterval(3);
                    $nleft_precio = $intervalo_precio['nleft'];
                    $nright_precio = $intervalo_precio['nright'];
                    
                    $sql_es_hija = 'SELECT id_category 
                    FROM lafrips_category                  
                    WHERE id_category = '.$id_category_default.'
                    AND
                    ((nleft > '.$nleft_kids.' AND nright < '.$nright_kids.')
                    OR 
                    (nleft > '.$nleft_adulto.' AND nright < '.$nright_adulto.'))
                    AND NOT (nleft > '.$nleft_precio.' AND nright < '.$nright_precio.')';
                    
                    $es_hija = Db::getInstance()->executeS($sql_es_hija);

                    if (count($es_hija) == 0) {
                        //no hay resultado luego no es hija de las que queremos
                        $id_category_default = 2501;
                    }
                }  

                //calculamos la categoría de precio
                if ($pvp > 30) {
                    $cat_precio = 15;
                } else if ($pvp < 15) {
                    $cat_precio = 13;
                } else {
                    $cat_precio = 14;
                }

                //obtenemos las categorías seleccionadas que había almacenadas en un input hidden
                $ids_categorias = Tools::getValue('ids_categorias', false);
                //si ids_categorias viene vacio creamos el array y le metemos Creador flash y precio, si no, pasamos lo que viene a array y le añadimos la de precio y creador flash
                if (!$ids_categorias) {
                    $categorias_secundarias = array();
                    array_push($categorias_secundarias, $cat_precio); 
                    array_push($categorias_secundarias, 2501);
                } else {
                    $categorias_secundarias = explode(',', $ids_categorias);
                    array_push($categorias_secundarias, $cat_precio); 
                    //también metemos Creador Flash
                    array_push($categorias_secundarias, 2501);  
                }

                // $product->id_category = [$ids_categorias.','.$cat_precio];
                $product->id_category = $categorias_secundarias;
                $product->id_category_default = $id_category_default;
            } else {
                //asignamos solo la categoría Creador Flash , 2501 en producción, 2449 test
                $product->id_category = [2501];
                $product->id_category_default = 2501;
            }

            $product->reference = $referencia_producto;
            //ponemos peso 1.111 por defecto en el formulario, para crearlos con peso y que este sea fácil de buscar posteriormente, si es que no ha introducido nada
            $product->weight = $peso;

            //Creamos los productos desactivados por defecto
            $product->active = 0;
            $product->id_manufacturer = $id_manufacturer;
            //por defecto tax 21% id 41 en formulario
            $product->id_tax_rules_group = $taxes;
            $product->id_supplier = $id_supplier;
            $product->ean13 = $ean;
            //La referencia de proveedor solo la añadimos por proveedor, no en lafrips_product
            //$product->supplier_reference = $referencia_proveedor;

            $product->redirect_type = '404';
            $product->advanced_stock_management = 1;

            //Si el producto se ha creado, ya dispone de id_product y se pueden añadir categorías, proveedor, almacén, etc
            if($product->add()){       
                //Asignar las categorías
                $product->updateCategories($product->id_category);
                
                //Asignar proveedor
                $product_supplier = new ProductSupplier();
                $product_supplier->id_product = $product->id;
                $product_supplier->id_product_attribute = 0;
                $product_supplier->id_supplier = $id_supplier;
                $product_supplier->product_supplier_reference = $referencia_proveedor;
                $product_supplier->product_supplier_price_te = $coste;                
                $product_supplier->id_currency = 1;
                $product_supplier->save();                

                //Asignar que las cantidades disponibles se basen en gestión avanzada (id_product, depends_on_stock, id_shop, id_product_attribute)                
                StockAvailable::setProductDependsOnStock((int)$product->id, 1, 1, 0);

                //Creamos el producto con la funcionalidad "Tipo de producto" que se haya seleccionado en select ('otros' por defecto)
                $feature_id = 8;
                //$feature_value_id = 155;
                Product::addFeatureProductImport($product->id, $feature_id, $feature_value_id);

                //si el producto no tiene combinaciones, solo falta asignar el almacén, si no hay que crearlas y asignarles también el almacén
                if ((Tools::getValue('producto_combinaciones_si') == 'on') && $combinaciones) {
                    //por cada combinación en $combinaciones, creamos en el producto un combinationEntity. Todas con el mismo coste, sin impactos de precio o peso, de momento. newCombinationEntity devuelve el id_product_attribute de la combinación creada
                    //si en el campo default ponemos a todos null, parece que luego si no se asigna una combinación como por defecto, salen sin precio, así que a la primera combinación le voy a poner valor 1 (primera vuelta) usando un contador $contador_default
                    $contador_default = 1;
                    foreach ($combinaciones AS $combinacion){
                        if ($contador_default == 1) {
                            $default = 1;
                        } else {
                            $default = null;
                        }
                        $contador_default++;
                        //tabla lafrips_product_attribute
                        $idProductAttribute = $product->addCombinationEntity(
                            $coste, //wholesale price
                            (float)0, //price 
                            (float)0, //weight
                            0,        //unit_impact
                            null ,    //ecotax
                            (int)0,   //quantity
                            "",       //id_images
                            $combinacion['combination_reference'] , //referencia para la combinación
                            $id_supplier, //supplier
                            $combinacion['combination_ean'], //ean13
                            $default, //default 
                            NULL,  //location
                            NULL  //upc 
                        );
                        // Al tener el id_product_atribute, le asignamos a la combinación la gestión avanzada (id_product, depends_on_stock, id_shop, id_product_attribute) 
                        StockAvailable::setProductDependsOnStock((int)$product->id, $product->depends_on_stock, 1, (int)$idProductAttribute);
                        //StockAvailable::setProductOutOfStock((int)$product->id, $product->out_of_stock, 1, (int)$idProductAttribute);

                        //a cada combinación (id_product_attribute) le asignamos el supplier, el (int)$idProductAttribute, la referencia de proveedor correspondiente, el coste y el id_currency(1) . Tabla lafrips_product_supplier
                        $product->addSupplierReference($id_supplier, (int)$idProductAttribute, $combinacion['combination_supplier_reference'], $coste, 1);          
                        //ahora insertamos el id_attribute que corresponde a cada id_product_attribute
                        Db::getInstance()->execute('
                            INSERT INTO lafrips_product_attribute_combination (`id_attribute`, `id_product_attribute`)
                            VALUES ('.$combinacion['id_attribute'].','.$idProductAttribute.')');

                        //Asignar almacén online a cada combinación
                        $product_warehouse = new WarehouseProductLocation();
                        $product_warehouse->id_product = $product->id;
                        $product_warehouse->id_product_attribute = $idProductAttribute;
                        $product_warehouse->id_warehouse = 1;
                        //$product_warehouse->location = ''; 
                        $product_warehouse->save();

                        //metemos un log por cada combinación
                        Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                            (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                            VALUES ('Combinación Producto Flash',".$product->id.",'".$combinacion['combination_reference']."','".$combinacion['combination_ean']."','".$combinacion['combination_supplier_reference']."',".$id_supplier.",
                                    '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");

                    }
                } else {
                    //No hay combinaciones, solo Asignar almacén online al producto base
                    $product_warehouse = new WarehouseProductLocation();
                    $product_warehouse->id_product = $product->id;
                    $product_warehouse->id_product_attribute = 0;
                    $product_warehouse->id_warehouse = 1;
                    //$product_warehouse->location = ''; 
                    $product_warehouse->save();
                }

                //si se ha marcado que tenga imagenes, sacamos el contenido de cada input de imagen, y con esa url subimos las imagenes          
                if (Tools::getValue('producto_imagenes_si') == 'on') {
                    //comprobamos una a una. La última es la del input cover_image_1, la pongo última para que quede arriba dentro del producto, ya que aparecen por orden de creación(parece que da igual). Se supone que si llega hasta aquí una url ha sido validada con javascript como url válida y con fin de url .jpg, .png o . gif (con o sin mayúsculas)     
                    //15/12/2021 Con algunos proveedores tenemos problemas a la hora de llevar la imagen a prestashop, no sé si sus servidores han cambiado algo pero por ejemplo con Karactermanía ya no se pueden crear las imágenes. Hago el paso previo de descargarlas a nuestro servidor y luego desde ahí subirlas a prestashop. Hay que utilizar curl, con file_get_contents tampoco acepta. El proceso de bajar la imagen al servidor lo hago en otra función descargaImagen()

                    if (trim(Tools::getValue('image_2'))) {
                        $url_imagen = trim(Tools::getValue('image_2'));

                        //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                        $url_imagen = $this->descargaImagen($url_imagen);

                        //como no es cover enviamos $cover = false
                        $this->gestionImagen($product->id, $url_imagen , false);
                    }

                    if (trim(Tools::getValue('image_3'))) {
                        $url_imagen = trim(Tools::getValue('image_3'));

                        //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                        $url_imagen = $this->descargaImagen($url_imagen);

                        //como no es cover enviamos $cover = false
                        $this->gestionImagen($product->id, $url_imagen , false);
                    }

                    if (trim(Tools::getValue('image_4'))) {
                        $url_imagen = trim(Tools::getValue('image_4'));

                        //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                        $url_imagen = $this->descargaImagen($url_imagen);

                        //como no es cover enviamos $cover = false
                        $this->gestionImagen($product->id, $url_imagen , false);
                    }

                    if (trim(Tools::getValue('image_5'))) {
                        $url_imagen = trim(Tools::getValue('image_5'));

                        //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                        $url_imagen = $this->descargaImagen($url_imagen);

                        //como no es cover enviamos $cover = false
                        $this->gestionImagen($product->id, $url_imagen , false);
                    }

                    if (trim(Tools::getValue('image_6'))) {
                        $url_imagen = trim(Tools::getValue('image_6'));

                        //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                        $url_imagen = $this->descargaImagen($url_imagen);

                        //como no es cover enviamos $cover = false
                        $this->gestionImagen($product->id, $url_imagen , false);
                    }
                    //imagen principal
                    if (trim(Tools::getValue('cover_image_1'))) {
                        $url_imagen = trim(Tools::getValue('cover_image_1'));

                        //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                        $url_imagen = $this->descargaImagen($url_imagen);

                        //como es cover enviamos $cover = true
                        $this->gestionImagen($product->id, $url_imagen , true);
                    }

                }


                //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos. Si era un producto sin combinaciones saldrá lo de siempre, pero si tiene combinaciones, saldrán primero las combinaciones con su ref de proveedor, etc y despuñes esta, que será el producto en general   

                //si el producto tiene combinaciones, en log no metemos referencia de proveedor sino 'Producto con combinaciones', e indicamos que era producto con combinaciones
                $accion_realizada = 'Crear Producto Flash';
                if (Tools::getValue('producto_combinaciones_si') == 'on') {
                    $referencia_proveedor = 'Producto Combinaciones';
                    $accion_realizada = 'Crear Producto Flash con Combinaciones';
                }

                Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                     (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                     VALUES ('".$accion_realizada."',".$product->id.",'".$referencia_producto."','".$ean."','".$referencia_proveedor."',".$id_supplier.",
                            '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");

                $this->confirmations[] = $this->l('Producto '.$referencia_producto.' creado. ');

                // $token = Tools::getAdminTokenLite('AdminCrearProductos');
                
                // $url_base = _PS_BASE_URL_.__PS_BASE_URI__;
                
                // $url = $url_base.'/lfadminia/index.php?controller=AdminCrearProductos?token='.$token;  
                
                // header("Location: $url");

                // $this->confirmations[] = $this->l('Producto '.$referencia_producto.' creado. ');

                

            } else {
                $this->errors[] = Tools::displayError('Error durante la creación de '.$referencia_producto);
            }             

            


            // $this->errors[] = Tools::displayError('Ese producto ya ha sido revisado');
            // $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
            // $this->displayWarning('El pedido '.$id_order.' contiene otros productos sin revisar. ');
        }

        //parent::postProcess();
    }

    //Función que descarga a nuestro servidor la imagen desde la url remota
    public function descargaImagen($url) {
        //directorio del servidor donde dejamos las fotos, habrá que limpiarlo de vez en cuando
        $download_path = '/var/www/vhost/lafrikileria.com/home/html/download/fotos/'; 

        $file_name = basename($url); 
        $url_servidor = $download_path.$file_name; //url con nombre de archivo en nuestro servidor

        // Initialize the cURL session
        $ch = curl_init($url);       
                
        // Open file
        $fp = fopen($url_servidor, 'wb');
        
        // It set an option for a cURL transfer
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        
        // Perform a cURL session
        curl_exec($ch);
        
        // Closes a cURL session and frees all resources
        curl_close($ch);
        
        // Close file
        fclose($fp);

        return $url_servidor;
    }    

    //Función que gestiona la subida y guardado de imágenes
    public function gestionImagen($id_product, $url , $cover) {
        //generamos la nueva entrada de imagen en lafrips_image. Se asocia el id_image al id_product (y si es cover  true o false) El id_image será la ruta a su carpeta en html/img/p/
        $image = new Image();
        $image->id_product = $id_product;
        $image->position = 1;
        $image->cover = $cover;

        //si se genera llamamos a la función de AdminImportController.php copyImg() Las he traido aquí porque no conseguía hacer include del controlador para usar sus funciones
        if ($image->add()){
            // true se envia como $regenerate indicando que tiene que generar todas las imágenes (home_default, large_default, etc)            
            if ($this->copyImg($id_product, $image->id, $url, 'products', true))
            {
                //si la imagen se ha subido correctamente, tendremos en lafrips_image_lang una línea por cada id_lang activo, para el title, metemos el nombre del producto a todos los idiomas
                $product = new Product($id_product);
                $title = $product->name; //utilizamos $title[1] por que es un array con uno por id_lang y queremos español, id_lang 1
                Db::getInstance()->execute('UPDATE lafrips_image_lang
							SET legend ="'.$title[1].'"
                            WHERE id_image = '.(int)$image->id);
                            
                $this->confirmations[] = $this->l('Imágenes subidas correctamente');
            }else{                
                $image->delete();
                $this->errors[] = Tools::displayError('Error generando la imagen de la url: '.$url);
            }   
        }
    }

    //función de AdminImportController.php Las he traido aquí porque no conseguía hacer include del controlador para usar sus funciones
    public function copyImg($id_entity, $id_image = null, $url, $entity = 'products', $regenerate = true)
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity) {
            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();
            break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_.(int)$id_entity;
            break;
            case 'manufacturers':
                $path = _PS_MANU_IMG_DIR_.(int)$id_entity;
            break;
            case 'suppliers':
                $path = _PS_SUPP_IMG_DIR_.(int)$id_entity;
            break;
        }

        $url = urldecode(trim($url));
        $parced_url = parse_url($url);

        if (isset($parced_url['path'])) {
            $uri = ltrim($parced_url['path'], '/');
            $parts = explode('/', $uri);
            foreach ($parts as &$part) {
                $part = rawurlencode($part);
            }
            unset($part);
            $parced_url['path'] = '/'.implode('/', $parts);
        }

        if (isset($parced_url['query'])) {
            $query_parts = array();
            parse_str($parced_url['query'], $query_parts);
            $parced_url['query'] = http_build_query($query_parts);
        }

        if (!function_exists('http_build_url')) {
            require_once(_PS_TOOL_DIR_.'http_build_url/http_build_url.php');
        }

        $url = http_build_url('', $parced_url);

        $orig_tmpfile = $tmpfile;

        if (Tools::copy($url, $tmpfile)) {
            // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
            if (!ImageManager::checkImageMemoryLimit($tmpfile)) {
                @unlink($tmpfile);
                return false;
            }

            $tgt_width = $tgt_height = 0;
            $src_width = $src_height = 0;
            $error = 0;
            ImageManager::resize($tmpfile, $path.'.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5,
                                 $src_width, $src_height);
            $images_types = ImageType::getImagesTypes($entity, true);

            if ($regenerate) {
                $previous_path = null;
                $path_infos = array();
                $path_infos[] = array($tgt_width, $tgt_height, $path.'.jpg');
                foreach ($images_types as $image_type) {
                    //en AdminImportcontroller aquí utiliza self::get_best_path pero como no estamos en ese controlador, he traido la función get_best_path() justo debajo de esta
                    $tmpfile = $this->get_best_path($image_type['width'], $image_type['height'], $path_infos);

                    if (ImageManager::resize($tmpfile, $path.'-'.stripslashes($image_type['name']).'.jpg', $image_type['width'],
                                         $image_type['height'], 'jpg', false, $error, $tgt_width, $tgt_height, 5,
                                         $src_width, $src_height)) {
                        // the last image should not be added in the candidate list if it's bigger than the original image
                        if ($tgt_width <= $src_width && $tgt_height <= $src_height) {
                            $path_infos[] = array($tgt_width, $tgt_height, $path.'-'.stripslashes($image_type['name']).'.jpg');
                        }
                        if ($entity == 'products') {
                            if (is_file(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'.jpg')) {
                               unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'.jpg');
                            }
                            if (is_file(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'_'.(int)Context::getContext()->shop->id.'.jpg')) {
                               unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'_'.(int)Context::getContext()->shop->id.'.jpg');
                            }
                        }
                    }
                    if (in_array($image_type['id_image_type'], $watermark_types)) {
                        Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                    }
                }
            }
        } else {
            @unlink($orig_tmpfile);
            return false;
        }
        unlink($orig_tmpfile);
        return true;
    }

    //función de AdminImportController.php Las he traido aquí porque no conseguía hacer include del controlador para usar sus funciones. A esta función se la llama desde dentro de copyImg()
    public function get_best_path($tgt_width, $tgt_height, $path_infos)
    {
        $path_infos = array_reverse($path_infos);
        $path = '';
        foreach ($path_infos as $path_info) {
            list($width, $height, $path) = $path_info;
            if ($width >= $tgt_width && $height >= $tgt_height) {
                return $path;
            }
        }
        return $path;
    }

    /*
    * 14/09/2021 función que recibe uno o dos parámetros, el nombre en español y si hay en inglés, para generar el array de lenguaje para crear el producto en función de los idiomas en Prestashop. Para portugués se pone español también
    *
    */
    public function generaIdiomas($spanish, $english = '') {

        if (!$spanish) {
            return false;
        }
    
        if (!$english) {
            $english = $spanish;
        }
    
        $idiomas = Language::getLanguages();
    
        $todos = array();
        foreach ($idiomas as $idioma) {
            if (($idioma['iso_code'] == 'es') || ($idioma['iso_code'] == 'pt')) {
                $todos[$idioma['id_lang']] = $spanish;
            } else {
                $todos[$idioma['id_lang']] = $english;
            }
        }
    
        return $todos;

    }



}
