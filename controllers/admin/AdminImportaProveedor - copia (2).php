<?php
/**
 * Importador de productos desde catálogo de proveedor
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_'))
    exit;

class AdminImportaProveedorController extends ModuleAdminController {
    protected $limit = 10;

    public function __construct() {
        require_once (dirname(__FILE__) .'/../../importaproveedor.php');        

        $this->lang = false;
        $this->bootstrap = true;
        $this->module = new Importaproveedor();
        $this->table = 'product';
        $this->identifier = 'id_product';
        $this->className = 'Product';
        $this->allow_export = false;
        $this->delete = false;
        $this->context = Context::getContext();
        $this->default_lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

        parent::__construct();

        return true;
    }

    /**
     * AdminController::init() override
     * @see AdminController::init()
     */
    public function init() {
        $this->display = 'add';
        parent::init();
    }

    public function postProcess() {

        return parent::postProcess();
    }

    /**
     * AdminController::renderForm() override
     * @see AdminController::renderForm()
     */
    public function renderForm() {
        
        $this->toolbar_title = 'Importador de productos desde catálogo de proveedor';

        //Sacar los proveedores disponibles en la tabla frik_import_catalogos para meterlos en un Select
        $proveedores_tabla = 'SELECT DISTINCT id_proveedor AS id_supplier, nombre_proveedor AS name 
        FROM frik_import_catalogos';

        $suppliers = Db::getInstance()->ExecuteS($proveedores_tabla);       

        //$this->displayWarning(print_r($results));

        if (!$suppliers || empty($suppliers))
            $this->displayWarning('No se encontraron proveedores');

        //introducir un campo sin proveedor al Select de proveedores, se trata de un array que contiene el array a añadir (merge)
        $proveedor_vacio = array(array('id_supplier'=> 0, 'name'=> 'Selecciona proveedor'));
        $suppliers = array_merge($proveedor_vacio, $suppliers);

        //06/04/2022 limite de productos a mostrar. añadimos un select para decidir si mostramos 100 - 300 - 500 productos
        $limite_productos = array(
            array('id_limite'=> 30, 'name'=> 30),
            array('id_limite'=> 100, 'name'=> 100),
            array('id_limite'=> 300, 'name'=> 300),
            array('id_limite'=> 500, 'name'=> 500),
        );

        $this->fields_form = array(
            'legend' => array(
                'title' => 'BUSCAR PRODUCTOS EN CATÁLOGO DE PROVEEDOR',
                'icon' => 'icon-pencil'
            ),
            'input' => array(
                //Select con los proveedores disponibles en tabla de importación de catálogos
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
                    'hint' => 'Selecciona el proveedor cuya referencia quieres buscar, estos son los disponibles en la tabla de catálogos',
                ),
                //input para introducir nombre a buscar en la tabla
                array(
                    'type' => 'text',
                    'label' => 'Nombre de producto',
                    'name' => 'nombre_producto',
                    'required' => false, 
                    'hint' => 'Introduce el nombre del producto a buscar',
                    ),  
                //input para introducir la referencia de proveedor a buscar en la tabla
                array(
                    'type' => 'text',
                    'label' => 'Referencia de proveedor',
                    'name' => 'referencia_proveedor',
                    'required' => false, 
                    'hint' => 'Introduce la referencia de proveedor del producto a buscar',
                    ),  
                //input para buscar un ean en la tabla   
                array(
                    'type' => 'text',
                    'label' => 'Ean de producto',
                    'name' => 'ean',
                    'required' => false, 
                    'hint' => 'Introduce el ean del producto a buscar',
                    ),  
                array(
                    'type' => 'switch',                        
                    'label' => $this->l('Ocultar productos existentes'),
                    'name' => 'ocultar_existentes',
                    // 'is_bool' => true,
                    'desc' => $this->l('Mostrar solo productos de proveedores que no existen en Prestashop'),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ), 
                //07/02/2023 Añadir un switch para quitar productos que en principio no tienen stock en el proveedor, mostrar solo disponibles, aunque no sea muy fiable
                array(
                    'type' => 'switch',                        
                    'label' => $this->l('Ocultar productos no disponibles'),
                    'name' => 'ocultar_no_disponibles',
                    // 'is_bool' => true,
                    'desc' => $this->l('Mostrar solo productos de proveedores que tienen disponibilidad en el catálogo. DATO NO FIABLE.'),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ), 
                //limite de productos a mostrar. Tiene trampa, yo sacaré siempre hasta 1000, pero mostraré los que se seleccione. Esto es debido a que primero saco los productos y después, si hay que ocultar los que ya existen, los quito. De modo que si marcas mostrar 100 y los 100 primeros ya existen y está marcado ocultar existentes, no saldría ninguno, a pesar de que puede haber cientos, solo que no salen en la select inicial que tiene limit 100
                array(
                    'type' => 'select',
                    'label' => 'Límite productos',
                    'name' => 'limite_productos',
                    'required' => true,
                    'options' => array(
                        'query' => $limite_productos,
                        'id' => 'id_limite',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona el número máximo de productos a mostrar',
                ),                         
                ),
            'reset' => array('title' => 'Limpiar', 'icon' => 'process-icon-eraser icon-eraser'),   
            'submit' => array('title' => 'Buscar', 'icon' => 'process-icon-search icon-search'),            
        );
        
        $this->displayInformation(
                'Escoge el proveedor e introduce la referencia del producto en su catálogo para importarlo<br> '
        );
        return parent::renderForm();
    }

    /**
     * method call when ajax request is made for search product according to the search
     */
    public function ajaxProcessGetProducts() {
        
        $id_lang = (int) $this->context->language->id;
        
        //accedemos a los valores de select e inputs almacenados en back.js dentro de dataObj
        $pattern_referencia = trim(pSQL(Tools::getValue('referencia_proveedor', false)));
        $pattern_ean = trim(pSQL(Tools::getValue('ean', false)));
        $id_supplier = (int) Tools::getValue('id_supplier', false);
        $pattern_nombre = trim(pSQL(Tools::getValue('nombre_producto', false)));
        //04/04/2022 mostrar o no los productos de los catálogos que ya existen en prestashop. true es ocultar, false es mostrarlos
        $ocultar_existentes = Tools::getValue('ocultar_existentes', false); 
        $limite_productos = (int) Tools::getValue('limite_productos', false);   
        //07/02/2023 mostrar o no los productos de los catálogos que tienen disponibilidad. true es ocultar sin disponibilidad, false es mostrarlos
        $ocultar_no_disponibles = Tools::getValue('ocultar_no_disponibles', false);             
        
        if ((!$pattern_nombre || $pattern_nombre == '')&&(!$pattern_referencia || $pattern_referencia == '')&&(!$pattern_ean || $pattern_ean == '')){               
           // $errors[] = 'Introduce lo que estás buscando';
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Introduce lo que estás buscando')));
        }

        // if (($pattern_referencia || $pattern_referencia !== '')&&($pattern_ean || $pattern_ean !== '')){
        //     //$errors[] = 'Rellena solo uno de los campos';
        //     die(Tools::jsonEncode(array('error'=> true, 'message'=>'Rellena solo uno de los campos')));
        // }
        
        //como los ean de Heo a veces son de más de 13 cifras o incluso contienen letras... permitimos cualquier cosa en el input
        //permitimos que el ean sea de hasta 25 cifras por los de Heo, pero que sea número
        // if ($pattern_ean && !(preg_match("/^[0-9]{1,25}$/", $pattern_ean))){                
        //     //$errors[] = 'El número Ean tiene que ser un número, de hasta 13 cifras, obviamente...';
        //     die(Tools::jsonEncode(array('error'=> true, 'message'=>'El número Ean tiene que ser un número, de hasta 13 cifras.')));
        // }

        //Si hay errores los muestra y vuelve
        /*if ($errors){
            foreach ($errors as $error){
                $this->displayWarning($error);
            }

            return false;
        }*/

        //Construir SELECT a BD
        //Si no se selecciona proveedor buscará en todos
        if ($id_supplier !== 0){
            $proveedor_select = ' AND id_proveedor ='.$id_supplier;
        }else{
            $proveedor_select = '';
        }

        //Si escribe o no en casilla nombre
        if ($pattern_nombre !== ''){
            $nombre_select = ' nombre LIKE \'%'.$pattern_nombre.'%\' ';
        }else{
            $nombre_select = '';
        }

        //Si pone referencia de producto en proveedor
        if ($pattern_referencia !== ''){
            $referencia_select = ' referencia_proveedor LIKE \'%'.$pattern_referencia.'%\' ';
        }else{
            $referencia_select = '';
        }

        //Si pone ean 
        if ($pattern_ean !== ''){
            //20/06/2022 Se da un problema recurrente, que si el usuario busca un ean tal que 01234567890123, es decir, 13 cifras pero con 0 en el inicio, es fácil que se corresponda al mismo número pero sin el 0 y de 12 cifras para un proveedor y el de 13 para otro. Eso no es problema si la búsqueda es del ean de 12 cifras al poner LIKE, ya que sustituye el primer carácter, pero no funcionará si ponemos el 0 en la búsqueda y el encontrado no tiene el 0. Vamos a analizar los ean y si tiene 13 cifras y además la primera es 0 , lo sustituimos en la búsqueda por el ean sin el 0 inicial.
            if (strlen($pattern_ean) == 13 && substr($pattern_ean,0,1) == 0) {
                //el ean introducido tiene 13 caracteres y el primero es 0, lo cambiamos por el mismo sin el 0
                $pattern_ean = substr($pattern_ean,1,12);
            }

            $ean_select = ' ean LIKE \'%'.$pattern_ean.'%\' ';
        }else{
            $ean_select = '';
        }

        //mostrar con disponibilidad o no 
        if ($ocultar_no_disponibles) {
            $disponibilidad = ' AND disponibilidad = 1 ';
        } else {
            $disponibilidad = '';
        }

        /*if ($pattern_referencia && !$pattern_ean){
            $condicion = 'WHERE referencia_proveedor LIKE \'%'.$pattern_referencia.'%\' '.$suppliers_select;
        }elseif ($pattern_ean && !$pattern_referencia){
            $condicion = 'WHERE ean LIKE \'%'.$pattern_ean.'%\' '.$suppliers_select;
        }else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error en los datos de busqueda')));
        }*/

        $condicion = '';
        if ($nombre_select !== ''){
            $condicion .= $nombre_select;
        }

        if (($referencia_select !== '')&&($nombre_select !== '')){
            $condicion .= ' AND '.$referencia_select;
        }elseif (($referencia_select !== '')&&($nombre_select == '')){
            $condicion .= $referencia_select;
        }

        if ((($referencia_select !== '')||($nombre_select !== ''))&&($ean_select !== '')){
            $condicion .= ' AND '.$ean_select;
        }elseif ((($referencia_select == '')&&($nombre_select == ''))&&($ean_select !== '')){
            $condicion .= $ean_select;
        }

        $condicion .= $proveedor_select;

        $condicion .= $disponibilidad;

        //Añadimos que busque un máximo de 1000 productos si se ha marcado ocultar existentes, si no el limit será igual a $limite_productos. Después, en el foreach por cada línea, nos aseguramos de que muestre un máximo de $limite_productos. Esto es porque al poner el límite a la select, si seleccionamos ocultar existentes se puede dar incluso que con este limit todos existan y no se muestre ninguno porque los no existentes aparecen después
        if ($ocultar_existentes) {
            $limit = 1000;
        } else {
            $limit = $limite_productos; 
        }
        

        $busqueda = 'SELECT id_import_catalogos, id_proveedor, referencia_proveedor, ean, nombre, nombre_proveedor, descripcion, precio, url_producto, url_imagen, disponibilidad, fecha_llegada, atributo, eliminado
            FROM frik_import_catalogos 
            WHERE 
            '.$condicion.' LIMIT '.$limit.';';
        
        $resultado = Db::getInstance()->executeS($busqueda);

        //Vamos a buscar en cada línea del resultado de la query, si la referencia de proveedor o el ean o ambos de los productos resultado de la consulta se encuentran ya en algún o algunos productos en Prestashop, y si es así los añadiremos a $resultado para pasarlo por json y ajax. También vamos a buscar el ean si lo hay en la tabla import_catalogos para mostrar si el producto con ese ean lo tiene algún otro proveedor.
        // 04/04/2022 Si el switch se marcó como ocultar productos existentes evitaremos los que encontremos si ean o referencia en prestashop. Hacemos el foreach key=>value para poder hacer unset del array con el key
        //contaremos los productos a mostrar hasta un máximo de  $limite_productos
        $contador = 0;
        foreach ($resultado as $key => &$linea) {  
            if ($contador >=  $limite_productos) {
                //una vez alcanzado el límite de productos a mostrar, hacemos unset del resto de líneas en $resultado
                unset($resultado[$key]); 
                continue;
            }              
            //Si no se selecciona proveedor buscará en todos
            if ($id_supplier !== 0){
                $proveedor_select = ' AND id_supplier ='.$id_supplier;
            }else{
                $proveedor_select = '';
            }

            //La referencia de proveedor será la de cada producto que vaya a salir en el resultado de la búsqueda, es decir, una referencia completa, y no el pattern que se ha usado en la búsqueda, que podría no ser una referencia completa
            $referencia = $linea['referencia_proveedor'];
            //buscamos la referencia de proveedor en nuestra BD. Si no se ha seleccionado proveedor buscamos en general, aunque puede dar lugar a error. No diferenciamos si tiene atributo y hacemos DISTINCT
            $busca_ref_prov = "SELECT GROUP_CONCAT(DISTINCT id_product SEPARATOR '|') AS existentes FROM lafrips_product_supplier 
                WHERE product_supplier_reference = '".$referencia."' ".$proveedor_select." ;";  
            
            $existentes_referencia = Db::getInstance()->executeS($busca_ref_prov);             

            //Guardamos el resultado en la línea 
            $linea['prestashop_ref_prov'] = $existentes_referencia[0]['existentes'];  

            //Parecido para buscar los ean. Hacemos trim para evitar espacios en blanco, etc
            $ean = trim($linea['ean']); 

            //Si el producto del proveedor no tiene ean nos saltamos este paso y mostrará 'No'
            if ((!empty($ean))&&($ean !== '')){
                //20/06/2022 Se da un problema recurrente, que si el usuario busca un ean tal que 01234567890123, es decir, 13 cifras pero con 0 en el inicio, es fácil que se corresponda al mismo número pero sin el 0 y de 12 cifras para un proveedor y el de 13 para otro. Eso no es problema si la búsqueda es del ean de 12 cifras al poner LIKE, ya que sustituye el primer carácter, pero no funcionará si ponemos el 0 en la búsqueda y el encontrado no tiene el 0. Vamos a analizar los ean y si tiene 13 cifras y además la primera es 0 , lo sustituimos en la búsqueda por el ean sin el 0 inicial.
                if (strlen($ean) == 13 && substr($ean,0,1) == 0) {
                    //el ean introducido tiene 13 caracteres y el primero es 0, lo cambiamos por el mismo sin el 0
                    $ean = substr($ean,1,12);
                }
                //buscamos el ean en la tabla product y en la tabla product_attribute, ponemos la búsqueda con LIKE para evitar los productos con ean de 12 cifras a las que algunos añaden un 0 delante
                $busca_ean = "SELECT GROUP_CONCAT(DISTINCT id_product SEPARATOR '|') AS existentes FROM
                                ((SELECT id_product
                                FROM lafrips_product
                                WHERE ean13 LIKE '%".$ean."')
                                UNION 
                                (SELECT id_product
                                FROM lafrips_product_attribute
                                WHERE ean13 LIKE '%".$ean."')) 
                                AS tabla_aux;";  

                $existentes_ean = Db::getInstance()->executeS($busca_ean);

                //Guardamos el resultado en la línea 
                $linea['prestashop_ean'] = $existentes_ean[0]['existentes']; 
            }else{
                $linea['prestashop_ean'] = null;
            }

            //06/04/2022 si en el front se seleccionó el switch para ocultar los productos que ya existen, $ocultar_existentes vale 1, hacemos unset de está línea de producto para sacarla del array de productos a mostrar cuando las select que buscan referencias de producto o ean den resultado, implicando que el producto ya existe
            if ($ocultar_existentes) {
                if ($linea['prestashop_ref_prov'] != null || $linea['prestashop_ean']  != null) {
                    //si hay info de producto existente encontrado por la referencia o el ean, eliminamos la línea y pasamos a la siguiente
                    unset($resultado[$key]); 
                    continue;
                }
            }

            //Para el proceso de agregar proveedor a productos que ya existan (coincide ean) comprobamos si el ean lo tiene un solo producto o más y también si el ean es de producto o de atributo. Si lo comparte más de un producto no permitiremos agregar el proveedor mientras sea así por no saber a cual, y si es de atributo, tampoco.
            if ((!empty($ean))&&($ean !== '')){
                //buscamos el ean en la tabla product
                $busca_ean_prod = "SELECT id_product
                                FROM lafrips_product
                                WHERE ean13 LIKE '%".$ean."%';";

                $existentes_ean = Db::getInstance()->executeS($busca_ean_prod);                

                if (empty($existentes_ean)){
                    $linea['ean_producto_unico'] = 0;
                }else{
                    //comprobamos si hay más de un producto con ese ean, si es así no se permitirá agregar proveedor
                    $num = 0;
                    foreach($existentes_ean as $existente){
                        $num++;
                    }
                    if ($num > 1){
                        $linea['ean_producto_unico'] = 1;
                    }else{
                        $linea['ean_producto_unico'] = 0;
                    }
                }


                //buscamos el ean en la tabla product_attribute
                $busca_ean_atr = "SELECT id_product
                                FROM lafrips_product_attribute
                                WHERE ean13 LIKE '%".$ean."%';";

                $existentes_ean_atr = Db::getInstance()->executeS($busca_ean_atr);

                //Si el ean corresponde a un atributo en prestashop no permitimos agregar proveedor
                if (empty($existentes_ean_atr)){
                    $linea['ean_atributo'] = 0;
                }else{
                    $linea['ean_atributo'] = 1;
                }

            }else{
                $linea['ean_producto_unico'] = 0;
                $linea['ean_atributo'] = 0;
            }


            //Sacamos los proveedores que tienen el producto con el ean de esta línea y que no sea el proveedor de esta línea. Se busca en la tabla de catálogos, poniendo el precio de cada uno también
            $proveedor = $linea['id_proveedor'];
            //el ean no debe estar vacio. Buscamos con LIKe para evitar los de 12 cifras a los que se les añade un 0 al principio
            if ((!empty($ean))&&($ean !== '')){
                $busca_proveedor = "SELECT GROUP_CONCAT(CONCAT(nombre_proveedor,' - ',ROUND(precio,2)) SEPARATOR ' / ') AS otros_prov
                FROM frik_import_catalogos 
                WHERE id_proveedor != ".$proveedor."
                    AND ean LIKE '%".$ean."%';";

                $otros_proveedores = Db::getInstance()->executeS($busca_proveedor);  
                
                //Guardamos el resultado en la línea
                $linea['otro_proveedor'] = $otros_proveedores[0]['otros_prov'];    
            }else{
                $linea['otro_proveedor'] = null;
            }

            //09/11/2021 Para las líneas de Cerdá, modificamos la url de la imagen ya que da error al intentar mostrarla, como página no segura.
            //url que nos pasan:
            //  http://images.cerdagroup.net.s3.eu-central-1.amazonaws.com/sources/2100002945.jpg
            //url "buena"
            //  https://s3.eu-central-1.amazonaws.com/images.cerdagroup.net/sources/2100002945.jpg
            //tenemos que coger la parte del final desde la barra 2100002945.jpg e intercambiar el resto 
            $url_imagen = $linea['url_imagen'];
            if ($url_imagen && $proveedor == 65) {
                $url_imagen = str_replace("http://images.cerdagroup.net.s3.eu-central-1.amazonaws.com/sources/", "https://s3.eu-central-1.amazonaws.com/images.cerdagroup.net/sources/", $url_imagen);

                $linea['url_imagen'] = $url_imagen;
            }

            $contador++;
        }

        //die(Tools::jsonEncode(array('error'=> true, 'message'=>$busqueda)));    
        //console.log($resultado);        

        //Si la consulta devuelve resultado, lo metemos en la variable para ajax 'products' y se codifica en json (esto será data.products para back.js)
        if ($resultado && !empty($resultado))
            die(Tools::jsonEncode(array('products'=> $resultado)));
        //Si la consulta no devuelve resultados se codifica json el error para ajax (que se mostrará desde back.js)
        die(Tools::jsonEncode(array('error'=> true, 'message'=>'No encuentro productos con esos datos')));
    }

    /**
     * AdminController::initContent() override
     * @see AdminController::initContent()
     */
    public function initContent() {
        $this->tpl_form_vars['currency'] = $this->context->currency;
        $this->tpl_form_vars['img_prod_dir'] = _THEME_PROD_DIR_;        
        parent::initContent();
    }

    /*
     *
     */
    public function setMedia(){
        parent::setMedia();
        $this->addJs($this->module->getPathUri().'views/js/back.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back.css');
    }

    /*
    * Función que creará el producto seleccionado con los datos proporcionados por el proveedor
    *
    */
    public function ajaxProcessCrearProducto(){
        //referencia introducida e id de producto en tabla de catalogos
        $referencia = Tools::getValue('referencia', 0);
        //recibimos el nombre asignado en el input
        $nombre_producto = trim(pSQL(Tools::getValue('nombre_producto')));
        //06/09/2023 recibimos la descripción del textarea
        $descripcion_producto = Tools::getValue('descripcion_producto');
        $id_producto = Tools::getValue('id', 0);
        if(!$id_producto)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se encuentra el producto.')));

        $response = true;        

        // die(Tools::jsonEncode(array('error'=> true, 'message'=>'check_importar= '.Tools::getValue('check_importar',0))));

        //Obtenemos los datos almacenados del producto por proveedor
        $sql_producto = 'SELECT ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, pvp, peso, url_imagen, url_imagen_2,
                             url_imagen_3, url_imagen_4, url_imagen_5, url_imagen_6
                            FROM frik_import_catalogos WHERE id_import_catalogos = '.$id_producto;

        $datos_producto = Db::getInstance()->executeS($sql_producto); 

        $mensaje_imagen ='';

        if ($datos_producto) {
            
            //$nombre_producto = $datos_producto[0]['nombre']; 
            $referencia_producto = $referencia;
            $referencia_proveedor = $datos_producto[0]['referencia_proveedor'];
            $nombre_proveedor = $datos_producto[0]['nombre_proveedor'];
            $id_proveedor = $datos_producto[0]['id_proveedor'];
            $peso = $datos_producto[0]['peso'];
            //desde 10/06/2020 la descripción la generamos al cargar el excel
            //06/09/2023 Ahora ponemos un textarea para modificarla al crearlo, con lo que el dato lo recibimos por formulario y está en $descripcion_producto
            // $descripcion = $datos_producto[0]['descripcion'];

            //24/10/2023 Para productos Globomatik si queremos la descripción técnica ya que la tenemos bien almacenada, la pondremos en descripcion larga
            if ($id_proveedor == 156) {
                $descripcion_globomatik = $datos_producto[0]['descripcion'];
            }
            
            //13/07/2020 añadimos código para que intente añadir como imagen de producto la que tengamos almacenada en url_imagen. 20/07/2020 hemos añadido 5 campos más de imagen, si hay algo lo añadimos también al producto
            if ($datos_producto[0]['url_imagen']) {
                $url_imagen_cover = trim($datos_producto[0]['url_imagen']);
            } else {
                $url_imagen_cover = '';
            }
            $otras_imagenes = array();
            if ($datos_producto[0]['url_imagen_2']) {
                $otras_imagenes[] = trim($datos_producto[0]['url_imagen_2']);
            } 
            if ($datos_producto[0]['url_imagen_3']) {
                $otras_imagenes[] = trim($datos_producto[0]['url_imagen_3']);
            } 
            if ($datos_producto[0]['url_imagen_4']) {
                $otras_imagenes[] = trim($datos_producto[0]['url_imagen_4']);
            } 
            if ($datos_producto[0]['url_imagen_5']) {
                $otras_imagenes[] = trim($datos_producto[0]['url_imagen_5']);
            } 
            if ($datos_producto[0]['url_imagen_6']) {
                $otras_imagenes[] = trim($datos_producto[0]['url_imagen_6']);
            } 

            /*
            //generamos la descripción en función de los datos que tenemos de cada proveedor
            //enlace a web de proveedor
            if ($id_proveedor == 24){
                $enlace_web = $datos_producto[0]['descripcion'];
            }else if($id_proveedor == 4){
                $enlace_web = 'https://www.heo.com/de/es/product/'.$referencia_proveedor;                
            }else if($id_proveedor == 53){
                $enlace_web = 'https://karactermania.com/es/catalogsearch/result?query='.$referencia_proveedor;                
            }else if($id_proveedor == 14){
                $enlace_web = 'http://trade.abyssecorp.com/e/en/recherche?controller=search&orderby=date_add&orderway=desc&search_query='.$referencia_proveedor;                
            }else if($id_proveedor == 8){ //ERIK - 05/06/2020
                $enlace_web = 'https://www.grupoerik.com/es/buscar?controller=search&orderby=position&orderway=desc&search_query='.$referencia_proveedor.'&submit_search=';                
            }

            //descripcion
            if ($id_proveedor == 24){
                $descripcion = $enlace_web;
            }elseif ($id_proveedor == 4){
                $descripcion = $datos_producto[0]['descripcion'].' <br><br> Peso= '.$peso.' <br><br>    '.$enlace_web;
            }else{
                $descripcion = $datos_producto[0]['descripcion'].' <br><br>    '.$enlace_web;
            }
            */

            //comprobamos que el ean sea numérico y de máximo 13 cifras, ya que es lo que admite el campo de Prestashop
            $ean = trim($datos_producto[0]['ean']);            
            
            if (!preg_match("/^[0-9]{1,13}$/", $ean)){
                $ean = '';
            }
            
            $precio_proveedor = $datos_producto[0]['precio'];
            //pvp, por seguridad le ponemos por defecto el doble del coste salvo para Cerdá, que ya lo trae
            if (($id_proveedor == 65) && ($datos_producto[0]['pvp'] != 0)) {
                $pvp_con_iva = $datos_producto[0]['pvp'];
                $pvp = round($pvp_con_iva/(1 + (21 / 100)),4); // Todos van al 21%, redondeo  a 4 decimales
            } else {
                $pvp = round($precio_proveedor*2, 2);  
            }                                 
            
            //otros fabricantes por defecto
            $id_fabricante = 34; 

            //07/10/2020 Vamos a hacer que a los productos Cerdá Lifestyle Adult se les asigne el fabricante Cerdá Adult para poder gestionar su disponibilidad de stock con el catálogo horario que nos envían. Si el proveedor es Cerdá le ponemos ese fabricante, ya que en la tabla catálogos solo están los productos de Adult
            if ($id_proveedor == 65) {
                //id de manufacturer by name
                $id_fabricante = (int)Manufacturer::getIdByName('Cerdá Adult');
            }

            
            //creamos link rewrite desde el name
            $linkrewrite = Tools::link_rewrite($nombre_producto);  
            
            //optimización motores de búsqueda
            $meta_titulo = 'Producto por 34,90€ – LaFrikileria.com';
            $meta_descripcion = 'Compra todos los artículos de - en nuestra tienda de regalos originales de Cine, Series de Tv, Videojuegos, Superhéroes, Manga y Anime';  
                        
            //antes de crear el producto comproibamos si se utiliza uno existenete para copiar sus categorías y descripción, seo tio etc
            //si no se copian categorías de otro producto se le pone solo Importados, si no se ponen las que tenía el producto escogido, asegurándose de que tenga una por defecto, si no Importados 2430. la categoría de precio la calcularemos al crear el producto según su precio
            $id_feature_value = 155; //ponemos por defecto
            $categorias_secundarias = [2430];
            $id_category_default = 2430;

            if (Tools::getValue('check_importar') == 'on') {
                //si se ha utilizado lo de copiar los datos de otro producto, el check está on y viene un input hidden con el id_product del producto a copiar
                $id_producto_copiar = (int) Tools::getValue('id_producto_copiar', 0);
                //comprobamos que llegue un categoría y que esta sea hija de Frikileria kids o Adultos, si no, ponemos importados 2430. Tampoco vale que sea Adultos o frikileria kids, tiene que ser hija y no de precio. Para ello buscamos el id de la categoría buscando solo entre las dichas.
                $id_category_default = (int) Tools::getValue('id_category_default', 0);
                if (!$id_category_default) {
                    $id_category_default = 2430;
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
                        $id_category_default = 2430;
                    }
                }  

                //calculamos la categoría de precio. $pvp es precio sin iva, ponemos por defecto 21%
                $pvp_con_21 = $pvp*1.21;
                if ($pvp_con_21 > 30) {
                    $cat_precio = 15;
                } else if ($pvp_con_21 < 15) {
                    $cat_precio = 13;
                } else {
                    $cat_precio = 14;
                }

                //obtenemos las categorías seleccionadas que había almacenadas en un input hidden
                $ids_categorias = Tools::getValue('ids_categorias', 0);
                //si ids_categorias viene vacio creamos el array y le metemos Creador flash y precio, si no, pasamos lo que viene a array y le añadimos la de precio y importados
                if (!$ids_categorias) {
                    $categorias_secundarias = array();
                    array_push($categorias_secundarias, $cat_precio); 
                    array_push($categorias_secundarias, 2430);
                } else {
                    $categorias_secundarias = explode(',', $ids_categorias);
                    array_push($categorias_secundarias, $cat_precio); 
                    //también metemos Importados
                    array_push($categorias_secundarias, 2430);  
                }

                if ($id_producto_copiar) {
                    //la descripción, nombre y seo solo en español
                    $sql_descripciones = 'SELECT name, description_short, meta_description, meta_title  FROM lafrips_product_lang WHERE id_lang = 1 AND id_product = '.$id_producto_copiar;
                    $descripciones = Db::getInstance()->executeS($sql_descripciones)[0];

                    if ($descripciones) {
                        $nombre_copia = $descripciones['name']; 
                        $descripcion_copia = $descripciones['description_short']; 
                        $meta_descripcion = $descripciones['meta_description'];
                        $meta_titulo = $descripciones['meta_title'];
    
                        //montamos la descripción uniendo a la que tenga el producto en el importador el nombre y descripción del porducto copiado
                        //06/09/2023 ya no cogemos la descripcion del producto modelo
                        // $descripcion = $descripcion.'<br><br>'.$nombre_copia.'<br>'.$descripcion_copia;
                    }                   

                    //tipo de producto 
                    $sql_feature_value = 'SELECT id_feature_value FROM lafrips_feature_product WHERE id_feature = 8 AND id_product = '.$id_producto_copiar;
                    $feature_value = Db::getInstance()->executeS($sql_feature_value)[0];
                    $id_feature_value = $feature_value['id_feature_value'];
                    if (!$id_feature_value) {
                        $id_feature_value = 155;
                    }


                }
            }
            // fin if copiar categorías etc

            //Creamos producto
            $product = new Product();
            $product->name = $this->generaIdiomas($nombre_producto);
            $product->link_rewrite = $this->generaIdiomas($linkrewrite);
            $product->meta_title = $this->generaIdiomas($meta_titulo);
            $product->meta_description = $this->generaIdiomas($meta_descripcion);
            $product->available_now = $this->mensajeAvailable($id_proveedor)[0];
            $product->available_later = $this->mensajeAvailable($id_proveedor)[1];                              
            
            //para el importador solo queremos poner el español, ya que tendrán que limpiar la descripción, y desde 03/11/2021 lo metemos en la descripción corta para que no se dejen datos abajo. 
            // $product->description = $this->generaIdiomas($descripcion);  
            // $product->description_short = array( 1 => $descripcion); 
            $product->description_short = array( 1 => $descripcion_producto); 

            //24/10/2023 Para productos Globomatik si queremos la descripción técnica ya que la tenemos bien almacenada, la pondremos en descripcion larga
            if ($id_proveedor == 156) {
                $product->description = $this->generaIdiomas($descripcion_globomatik);                
            }

            $product->price = $pvp;
            $product->wholesale_price = $precio_proveedor;
            //asignamos solo la categoría Importados , 2430 o si se marco el check, lo que venga
            $product->id_category =  $categorias_secundarias;
            $product->id_category_default = $id_category_default;

            $product->reference = $referencia_producto;
            //ponemos peso 1.111 por defecto, para crearlos con peso y que este sea fácil de buscar posteriormente
            //20/07/2020 para Cerdá usamos el peso proporcionado
            //04/02/2022 Respetamos el peso también para Globomatik y DMI
            //02/09/2022 pasamos de 1.111 por defecto a 0.444
            if (($id_proveedor == 65 || $id_proveedor == 156 || $id_proveedor == 160) && $peso && $peso != 0){
                $product->weight = $peso;
            } else {
                $product->weight = 0.444;
            }
            

            //Creamos los productos desactivados por defecto
            $product->active = 0;
            $product->id_manufacturer = $id_fabricante;            
            //ponemos por defecto ES standar 21%, para saber su id_tax_rules_group lo tenemos que obtener por BD ya que a veces cambia el id
            $sql_tax_21 = 'SELECT trg.id_tax_rules_group AS id_tax_rules_group
            FROM lafrips_tax_rules_group trg
            JOIN lafrips_tax_rule tar ON tar.id_tax_rules_group = trg.id_tax_rules_group
            JOIN lafrips_tax tax ON tax.id_tax = tar.id_tax
            WHERE trg.active = 1
            AND trg.deleted = 0
            AND tar.id_country = 6 
            AND tax.active = 1
            AND tax.deleted = 0
            AND tax.rate = 21';

            $tax_21 = Db::getInstance()->getValue($sql_tax_21);           

            $product->id_tax_rules_group = $tax_21;
            $product->id_supplier = $id_proveedor;
            $product->ean13 = $ean;
            //La referencia de proveedor solo la añadimos por proveedor, no en lafrips_product
            //$product->supplier_reference = $referencia_proveedor;

            $product->redirect_type = '404';
            $product->advanced_stock_management = 1;

            //Si el producto se ha creado, ya dispone de id_product y se pueden añadir categorías, proveedor, almacén, etc
            if($product->add()){       
                //Asignar las categorías
                $product->updateCategories($product->id_category);
                
                //Asignar proveedor. De momento productos sin atributos
                $product_supplier = new ProductSupplier();
                $product_supplier->id_product = $product->id;
                $product_supplier->id_product_attribute = 0;
                $product_supplier->id_supplier = $id_proveedor;
                $product_supplier->product_supplier_reference = $referencia_proveedor;
                $product_supplier->product_supplier_price_te = $precio_proveedor;
                //si quisieramos asignarle tipo de moneda según context
                /*if ($this->context->currency->id) {
                    $product_supplier->id_currency = (int)$this->context->currency->id;
                } else {
                    $product_supplier->id_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
                }*/
                $product_supplier->id_currency = 1;
                $product_supplier->save();

                //Asignar almacén
                $product_warehouse = new WarehouseProductLocation();
                $product_warehouse->id_product = $product->id;
                $product_warehouse->id_product_attribute = 0;
                $product_warehouse->id_warehouse = 1;
                //$product_warehouse->location = ''; 
                $product_warehouse->save();

                //Asignar que las cantidades disponibles se basen en gestión avanzada                
                StockAvailable::setProductDependsOnStock((int)$product->id, 1, 1, 0);

                //Creamos el producto con la funcionalidad "Tipo de producto" - "otros" por defecto
                $feature_id = 8;
                // $feature_value_id = 155;
                $feature_value_id = $id_feature_value;
                Product::addFeatureProductImport($product->id, $feature_id, $feature_value_id);

                //15/12/2021 Con algunos proveedores tenemos problemas a la hora de llevar la imagen a prestashop, no sé si sus servidores han cambiado algo pero por ejemplo con Karactermanía ya no se pueden crear las imágenes. Hago el paso previo de descargarlas a nuestro servidor y luego desde ahí subirlas a prestashop. Hay que utilizar curl, con file_get_contents tampoco acepta. El proceso de bajar la imagen al servidor lo hago en otra función descargaImagen()
                
                //metemos la imagen, si la acepta, la ponemos como cover, enviamos $cover = false                  
                if ($url_imagen_cover){                    
                    //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                    $url_imagen_cover = $this->descargaImagen($url_imagen_cover);

                    if ($this->gestionImagen($product->id, $url_imagen_cover , true)) {
                        $mensaje_imagen .= ' - Imagen principal subida correctamente';
                    } else {
                        $mensaje_imagen .= ' - LA IMAGEN PRINCIPAL NO PUDO AÑADIRSE';
                    }
                } 
                //el resto de imágenes no son cover, $cover false  
                if ($otras_imagenes) {
                    foreach ($otras_imagenes as $imagen){
                        if ($imagen) {
                            //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                            $imagen = $this->descargaImagen($imagen);

                            if ($this->gestionImagen($product->id, $imagen , false)) {
                                $mensaje_imagen .= ' - Imagen secundaria subida correctamente';
                            } else {
                                $mensaje_imagen .= ' - IMAGEN SECUNDARIA NO PUDO AÑADIRSE';
                            }
                        }                           
                    }
                }      
                
                //07/10/2020 Si el producto es Cerdá, y por tanto Cerdá Adult, introducimos los datos de id_producto, referencia prestashop, etc, en la tabla frik_catalogo_cerda_crear para poder gestionar su disponibilidad de stock automáticamente
                if ($id_proveedor == 65 && $id_fabricante == (int)Manufacturer::getIdByName('Cerdá Adult')) {
                    $sql_update_frik_catalogo_cerda_crear = 'UPDATE frik_catalogo_cerda_crear
                    SET
                    existe_prestashop = 1,
                    referencia_prestashop = "'.$referencia_producto.'",
                    id_product_prestashop = '.$product->id.',
                    date_upd = NOW()
                    WHERE ean = "'.$ean.'"
                    AND referencia = "'.$referencia_proveedor.'"';

                    Db::getInstance()->Execute($sql_update_frik_catalogo_cerda_crear);

                }           
                
                //04/02/2022 Si el producto es Globomatik, comprobamos si está en la tabla catalogo_globomatik, si está actualizamos campo id_product_prestashop, referencia_prestashop, etc, sino introducimos el producto con los datos de que disponemos, indicando en el campo nombre_en que se creó con importador. De este modo se seguirán gestionando stock y descuentos con el mismo proceso que con los productos creados masivamente
                if ($id_proveedor == 156) {
                    //comprobamos si el producto está en frik_catalogo_globomatik
                    $sql_existe_referencia_globomatik = 'SELECT id_catalogo_globomatik
                    FROM frik_catalogo_globomatik 
                    WHERE referencia = "'.$referencia_proveedor.'"';
                    
                    $existe_referencia = Db::getInstance()->ExecuteS($sql_existe_referencia_globomatik);
                    if ((count($existe_referencia) == 1) && ($existe_referencia[0]['id_catalogo_globomatik'] > 0)) { 
                        //el producto está en la tabla, hacemos update con referencia e id_product de Prestashop
                        $sql_update_frik_catalogo_globomatik = 'UPDATE frik_catalogo_globomatik
                        SET
                        existe_prestashop = 1,
                        ignorar = 0,
                        referencia_prestashop = "'.$referencia_producto.'",
                        id_product_prestashop = '.$product->id.',
                        date_upd = NOW()
                        WHERE id_catalogo_globomatik = '.$existe_referencia[0]['id_catalogo_globomatik'];

                        Db::getInstance()->Execute($sql_update_frik_catalogo_globomatik);
                    } else {
                        //no se encuentra la referencia de globomatik en su tabla, insertamos los datos que tenemos del producto 
                        //sacamos el nombre del producto para globomatik, que estaba en la select del importador
                        $nombre_globomatik = $datos_producto[0]['nombre'];

                        $sql_insert_datos_catalogo_globomatik = 'INSERT INTO frik_catalogo_globomatik
                        (ean, referencia, nombre_es, nombre_en, descripcion_es, existe_prestashop, referencia_prestashop, id_product_prestashop, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia_proveedor.'", "'.$nombre_globomatik.'", "Creado/Insertado desde Importador", "'.$descripcion_producto.'", 1, "'.$referencia_producto.'", '.$product->id.', NOW())';

                        Db::getInstance()->execute($sql_insert_datos_catalogo_globomatik);
                    }          
                }     

                //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
                $id_empleado = Context::getContext()->employee->id;
                $nombre_empleado = Context::getContext()->employee->firstname;
                Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                     (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                     VALUES ('Crear Producto',".$product->id.",'".$referencia_producto."','".$ean."','".$referencia_proveedor."',".$id_proveedor.",
                            '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");

            }                 
        }

        if($response)
            die(Tools::jsonEncode(array('message'=>'Producto creado correctamente, recuerda revisar su IVA y su peso'.$mensaje_imagen)));
    }

    /*
    * Función que añadirá el proveedor al producto ya existente seleccionado, con los datos proporcionados por el proveedor
    *
    */
    public function ajaxProcessAgregarProveedor(){
        $id_producto_catalogos = Tools::getValue('id', 0);
        if(!$id_producto_catalogos)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se encuentra el producto.')));

        $response = true;
        //Obtenemos los datos almacenados del producto por proveedor de la tabla de catálogos
        $sql_producto = 'SELECT ean, referencia_proveedor, id_proveedor, nombre_proveedor, precio
                            FROM frik_import_catalogos WHERE id_import_catalogos = '.$id_producto_catalogos;

        $datos_producto = Db::getInstance()->executeS($sql_producto); 

        $ean_prod_proveedor = trim($datos_producto[0]['ean']); 

        //si vamos a añadir el proveedor quiere decir que hemos encontrado coincidencia por el ean, luego debería haber un producto con el ean dado, pero ponemos if
        if ((!$ean_prod_proveedor)||($ean_prod_proveedor == '')) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Hay algún error, el producto no tiene ean')));
        }
        //Buscamos en prestashop el producto con el ean del producto de proveedor, de momento solo en lafrips_product ya que no agregamos atributos
        //20/06/2022 Se da un problema recurrente, que si el usuario busca un ean tal que 01234567890123, es decir, 13 cifras pero con 0 en el inicio, es fácil que se corresponda al mismo número pero sin el 0 y de 12 cifras para un proveedor y el de 13 para otro. Eso no es problema si la búsqueda es del ean de 12 cifras al poner LIKE, ya que sustituye el primer carácter, pero no funcionará si ponemos el 0 en la búsqueda y el encontrado no tiene el 0. Vamos a analizar los ean y si tiene 13 cifras y además la primera es 0 , lo sustituimos en la búsqueda por el ean sin el 0 inicial.
        if (strlen($ean_prod_proveedor) == 13 && substr($ean_prod_proveedor,0,1) == 0) {
            //el ean buscado tiene 13 caracteres y el primero es 0, lo cambiamos por el mismo sin el 0
            $ean_prod_proveedor = substr($ean_prod_proveedor,1,12);
        }
                
        $sql_producto_prestashop = 'SELECT id_product, reference
                                    FROM lafrips_product
                                    WHERE ean13 LIKE "%'.$ean_prod_proveedor.'"';
        
        $producto_prestashop = Db::getInstance()->executeS($sql_producto_prestashop);
        //die(Tools::jsonEncode(array('error'=> true, 'message'=>$producto_prestashop)));
        //Si no se obtiene ningún producto buscando el ean o se obtiene más de uno mostrar error
        if (!$producto_prestashop) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No encuentro el producto en Prestashop')));
        }elseif($producto_prestashop[1]['id_product']){
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Existe más de un producto en Prestashop con ese ean')));
        }else{

            //Asignar proveedor. De momento productos sin atributos
            //El producto ya existe, solo creamos su supplier asociado nuevo
            $id_proveedor = $datos_producto[0]['id_proveedor'];
            $nombre_proveedor = $datos_producto[0]['nombre_proveedor'];
            $id_producto = $producto_prestashop[0]['id_product'];
            $referencia_proveedor = $datos_producto[0]['referencia_proveedor'];
            $precio_proveedor = $datos_producto[0]['precio'];

            //almacenamos la referencia del producto existene en prestashop para la tabla log
            $referencia_presta = $producto_prestashop[0]['reference'];

            //Nos aseguramos de que el producto no tenga ya asociado el mismo proveedor pero con otra referencia (o sin ella)
            $sql_proveedores_producto = 'SELECT id_product
                                FROM lafrips_product_supplier
                                WHERE id_product = '.$id_producto.' AND id_supplier = '.$id_proveedor;

            $proveedores_producto = Db::getInstance()->executeS($sql_proveedores_producto);            

            if ($proveedores_producto){ 
                die(Tools::jsonEncode(array('error'=> true, 'message'=>'Comprueba los proveedores existentes del producto')));
            }


            $product_supplier = new ProductSupplier();
            $product_supplier->id_product = $id_producto;
            $product_supplier->id_product_attribute = 0;
            $product_supplier->id_supplier = $id_proveedor;
            $product_supplier->product_supplier_reference = $referencia_proveedor;
            $product_supplier->product_supplier_price_te = $precio_proveedor;        
            $product_supplier->id_currency = 1;
            $product_supplier->save();

            //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname;
            Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                 (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                 VALUES ('Agregar Proveedor',".$id_producto.",'".$referencia_presta."','".$ean_prod_proveedor."','".$referencia_proveedor."',".$id_proveedor.",
                        '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");
        }


        if($response)
            die(Tools::jsonEncode(array('message'=>'Proveedor añadido al producto '.$id_producto.' correctamente')));
    }

    /*
    * Función que creará el producto seleccionado con los datos proporcionados por el proveedor
    *
    */
    public function ajaxProcessCrearProductoAtributos(){
        $response = true;
        //para log al final
        $id_empleado = Context::getContext()->employee->id;
        $nombre_empleado = Context::getContext()->employee->firstname;
        
        //asignamos a $referencia el valor introducido por el usuario al crear el producto        
        $referencia = Tools::getValue('referencia');
        $nombre_producto = Tools::getValue('nombre_producto');
        //06/09/2023 recibimos la descripción del textarea
        $descripcion_producto = Tools::getValue('descripcion_producto');
        //asignamos a combianciones el contenido en el objeto enviado por ajax de combinaciones, que es un array con tantos arrays como combinaciones, cada una con el id_import_catalogos, la referecnia de atributo, y el id_attribute
        $combinaciones = Tools::getValue('combinaciones');        
        
        if(!$referencia || !$combinaciones)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error recibiendo la información')));

        $datos_atributos = array();
        //sacamos los datos de cada combinación
        foreach ($combinaciones AS $combinacion) {
            $id_import_catalogos = $combinacion['id_import_catalogos'];
            $id_atributte = $combinacion['id_atributo'];
            $referencia_combinacion = $combinacion['referencia_combinacion'];
            $sql_datos_atributos = 'SELECT ean, referencia_proveedor, id_proveedor, nombre_proveedor, descripcion, 
            precio, pvp, peso, url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5, url_imagen_6, '.$id_atributte.' AS id_attribute,"'.$referencia_combinacion.'" AS referencia_combinacion             
            FROM frik_import_catalogos WHERE id_import_catalogos = '.$id_import_catalogos;
            $result = Db::getInstance()->executeS($sql_datos_atributos);
            $datos_atributos[] = $result[0];
        }
        
        //die(Tools::jsonEncode(array('error'=> true, 'message'=>$datos_atributos[0]['referencia_proveedor'])));        

        //sacamos los datos de cada posible atributo. Almacenamos los ean y referencias de proveedor en sendos arrays para luego. Los demás datos también, pero comprobamos que id_proveedor y precio deben ser iguales para todos, si no es así, podrían ser atributos de productos diferentes y un error al seleccionarlos en el importador. Las descripciones, nombre, url, etc solo guardaremos una para la creación del producto, la última que entre en el for
        //25/11/2021 por si los atributos tienen diferentes costes ya no comprobaremos que sean iguales
        if($datos_atributos){  
            $combinaciones_eans = [];
            $combinaciones_referencias = [];
            $combinaciones_id_proveedor = [];
            $combinaciones_precio = [];
            $combinaciones_peso = [];
            $combinaciones_pvp = [];
            $combinaciones_nombre_proveedor = '';            
            // $combinaciones_descripcion = '';            
            $combinaciones_url_imagen_cover = '';
                        
            //si hay imágenes, deberían tener las mismas ya que van por producto y no combinación, de modo que cogeremos una de cada, como el nombre de proveedor. Las vamos metiendo al array y nos quedamos con el último array, por eso lo creamos dentro del foreach, sino se repetirían las fotos tantas veces como combinaciones

            foreach ($datos_atributos as $atributo){
                $combinaciones_otras_imagenes = [];
                //comprobamos que el ean sea numérico y de máximo 13 cifras, ya que es lo que admite el campo de Prestashop
                $ean = trim($atributo['ean']);
                if (!preg_match("/^[0-9]{1,13}$/", $ean)){
                    $ean = '';
                }
                $combinaciones_eans[] = $ean;
                $combinaciones_referencias[] =  $atributo['referencia_proveedor'];
                $combinaciones_id_proveedor[] = $atributo['id_proveedor'];
                $combinaciones_precio[] = (float)$atributo['precio'];
                $combinaciones_pvp[] = (float)$atributo['pvp'];
                $combinaciones_peso[] = (float)$atributo['peso'];
                $combinaciones_nombre_proveedor = $atributo['nombre_proveedor'];                
                // $combinaciones_descripcion = $atributo['descripcion'];                
                $combinaciones_url_imagen_cover = trim($atributo['url_imagen']);
                $combinaciones_otras_imagenes[] = trim($atributo['url_imagen_2']);
                $combinaciones_otras_imagenes[] = trim($atributo['url_imagen_3']);
                $combinaciones_otras_imagenes[] = trim($atributo['url_imagen_4']);
                $combinaciones_otras_imagenes[] = trim($atributo['url_imagen_5']);
                $combinaciones_otras_imagenes[] = trim($atributo['url_imagen_6']);
            }
            //die(Tools::jsonEncode(array('error'=> true, 'message'=>$combinaciones_eans)));
            //comprobamos que el contenido de los arrays de id_proveedor y precio es el mismo en cada uno. Para ello contamos los elementos dentro de cada uno después de hacer array_unique que elimina duplicados. Si todos eran iguales debería dar 1 . 25/11/2021 QUITADO para precio
            
            if (count(array_unique($combinaciones_id_proveedor)) !== 1){
                die(Tools::jsonEncode(array('error'=> true, 'message'=>'Comprueba que los productos escogidos pertenecen al mismo proveedor')));
            }

            //25/11/2021 No hacemos esa comprobación para el precio, ya que estoy adaptando el importador a combinaciones con diferentes precios (cerdá pets) dependiendo del tamaño. Habrá que guardar cada precio por atributo y asignarlo.
            // if (count(array_unique($combinaciones_precio)) !== 1){
            //     die(Tools::jsonEncode(array('error'=> true, 'message'=>'Las combinaciones tienen distinto precio. Comprueba que los atributos escogidos pertenecen al mismo producto')));
            // }


            //comprobamos que los eans sean diferentes, contamos el array primero y después de array_unique debería salir la misma cuenta. Al hacer array_unique se eliminan los repetidos del array! QUITADO - muchos productos no tienen ean...
            // if (count($combinaciones_eans) !== count(array_unique($combinaciones_eans))){
            //     die(Tools::jsonEncode(array('error'=> true, 'message'=>'Comprueba que los ean de los atributos son diferentes')));
            // }

            //tiene que haber el mismo número de eans (aunque sea vacio) que de referencias
            if (count($combinaciones_eans) !== count($combinaciones_referencias)){
                die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error en la selección, diferente número de eans y referencias')));
            }
             
            //Con estos datos comenzamos la creación del producto, la parte inicial es igual que para un producto sin combinaciones:
            //fabricante Otros fabricantes por defecto
            $id_fabricante = 34;
            $id_supplier = $combinaciones_id_proveedor[0];

            //07/10/2020 Vamos a hacer que a los productos Cerdá Lifestyle Adult se les asigne el fabricante Cerdá Adult para poder gestionar su disponibilidad de stock con el catálogo horario que nos envían. Si el proveedor es Cerdá le ponemos ese fabricante, ya que en la tabla catálogos solo están los productos de Adult
            if ($id_supplier == 65) {
                //id de manufacturer by name
                $id_fabricante = (int)Manufacturer::getIdByName('Cerdá Adult');
            }
            
            $nombre_proveedor = $combinaciones_nombre_proveedor;
            //creamos link rewrite desde el name
            $linkrewrite = Tools::link_rewrite($nombre_producto);

            // 25/11/2021 Asignamos el precio que venga por atributo ya que en algunos  productos cada combinación tiene un coste, pero para el pvp demomento nos quedamos con uno de lso precios por atributo, el más pequeño
            // $precio_proveedor = $combinaciones_precio[0];
            $precio_proveedor = min($combinaciones_precio);

            //pvp, por seguridad le ponemos por defecto el doble del coste salvo para Cerdá, que ya lo trae con iva
            //25/11/2021 en combinaciones_pvp tenemos todos los pvp de los atributos, si son diferentes, creamos el producto con pvp base del más bajo y a cada combinación se le pone su impacto en pvp, de modo que cogemos el valor min del array (si son iguales coge bien también)
            if (($id_supplier == 65) && (min($combinaciones_pvp) != 0)) {
                $pvp_con_iva = min($combinaciones_pvp);
                $pvp = round($pvp_con_iva/(1 + (21 / 100)),4); // Todos van al 21%, redondeo  a 4 decimales
            } else {
                $pvp = round($precio_proveedor*2, 2);  
            }   

            //desde 10/06/2020 la descripción la generamos al cargar el excel
            //06/09/2023 ahora la traemos del cuadro de dialogo al crear el producto, uniendo la descfipcion a alguna frase para la IA del redactor
            // $descripcion = $combinaciones_descripcion;
            $descripcion = $descripcion_producto;

            //optimización motores de búsqueda
            $meta_titulo = 'Producto por 34,90€ – LaFrikileria.com';
            $meta_descripcion = 'Compra todos los artículos de - en nuestra tienda de regalos originales de Cine, Series de Tv, Videojuegos, Superhéroes, Manga y Anime';  
                       
            //antes de crear el producto comproibamos si se utiliza uno existente para copiar sus categorías y descripción, seo tio etc
            //si no se copian categorías de otro producto se le pone solo Importados, si no se ponen las que tenía el producto escogido, asegurándose de que tenga una por defecto, si no Importados 2430. la categoría de precio la calcularemos al crear el producto según su precio
            $id_feature_value = 155; //ponemos por defecto
            $categorias_secundarias = [2430];
            $id_category_default = 2430;

            if (Tools::getValue('check_importar') == 'on') {
                //si se ha utilizado lo de copiar los datos de otro producto, el check está on y viene un input hidden con el id_product del producto a copiar
                $id_producto_copiar = (int) Tools::getValue('id_producto_copiar', 0);                
                //comprobamos que llegue una categoría y que esta sea hija de Frikileria kids o Adultos, si no, ponemos importados 2430. Tampoco vale que sea Adultos o frikileria kids, tiene que ser hija y no de precio. Para ello buscamos el id de la categoría buscando solo entre las dichas.
                $id_category_default = (int) Tools::getValue('id_category_default', 0);
                if (!$id_category_default) {
                    $id_category_default = 2430;
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
                        $id_category_default = 2430;
                    }
                }  

                //calculamos la categoría de precio. $pvp es precio sin iva, ponemos por defecto 21%
                $pvp_con_21 = $pvp*1.21;
                if ($pvp_con_21 > 30) {
                    $cat_precio = 15;
                } else if ($pvp_con_21 < 15) {
                    $cat_precio = 13;
                } else {
                    $cat_precio = 14;
                }

                //obtenemos las categorías seleccionadas que había almacenadas en un input hidden
                $ids_categorias = Tools::getValue('ids_categorias', 0);
                //si ids_categorias viene vacio creamos el array y le metemos Creador flash y precio, si no, pasamos lo que viene a array y le añadimos la de precio y importados
                if (!$ids_categorias) {
                    $categorias_secundarias = array();
                    array_push($categorias_secundarias, $cat_precio); 
                    array_push($categorias_secundarias, 2430);
                } else {
                    $categorias_secundarias = explode(',', $ids_categorias);
                    array_push($categorias_secundarias, $cat_precio); 
                    //también metemos Importados
                    array_push($categorias_secundarias, 2430);  
                }

                if ($id_producto_copiar) {
                    //la descripción, nombre y seo solo en español
                    $sql_descripciones = 'SELECT name, description_short, meta_description, meta_title  FROM lafrips_product_lang WHERE id_lang = 1 AND id_product = '.$id_producto_copiar;
                    $descripciones = Db::getInstance()->executeS($sql_descripciones)[0];

                    if ($descripciones) {
                        $nombre_copia = $descripciones['name']; 
                        $descripcion_copia = $descripciones['description_short']; 
                        $meta_descripcion = $descripciones['meta_description'];
                        $meta_titulo = $descripciones['meta_title'];
    
                        //montamos la descripción uniendo a la que tenga el producto en el importador el nombre y descripción del porducto copiado
                        //06/09/2023 ya no cogemos la descripcion del producto modelo
                        // $descripcion = $descripcion.'<br><br>'.$nombre_copia.'<br>'.$descripcion_copia;
                    }                   

                    //tipo de producto 
                    $sql_feature_value = 'SELECT id_feature_value FROM lafrips_feature_product WHERE id_feature = 8 AND id_product = '.$id_producto_copiar;
                    $feature_value = Db::getInstance()->executeS($sql_feature_value)[0];
                    $id_feature_value = $feature_value['id_feature_value'];
                    if (!$id_feature_value) {
                        $id_feature_value = 155;
                    }

                }
            }
            // fin if copiar categorías etc

            //Creamos producto
            $product = new Product();

            $product->name = $this->generaIdiomas($nombre_producto);
            $product->link_rewrite = $this->generaIdiomas($linkrewrite);
            $product->meta_title = $this->generaIdiomas($meta_titulo);
            $product->meta_description = $this->generaIdiomas($meta_descripcion);                     
            $product->available_now = $this->mensajeAvailable($id_supplier)[0];
            $product->available_later = $this->mensajeAvailable($id_supplier)[1];              
                       
            //para el importador solo queremos poner el español, ya que tendrán que limpiar la descripción, y desde 03/11/2021 lo metemos en la descripción corta para que no se dejen datos abajo. 
            // $product->description = $this->generaIdiomas($descripcion);  
            // $product->description_short = array( 1 => $descripcion);
            $product->description_short = array( 1 => $descripcion_producto); 
            
            $product->price = $pvp;
            $product->wholesale_price = $precio_proveedor;
            //asignamos solo la categoría Importados , 2430 en producción, 2376 test
            $product->id_category =  $categorias_secundarias;
            $product->id_category_default = $id_category_default;

            $product->reference = $referencia;

            //ponemos peso 1.111 por defecto, para crearlos con peso y que este sea fácil de buscar posteriormente
            //20/07/2020 para Cerdá usamos el peso proporcionado
            //25/11/2021 por si las combinaciones tienen diferente peso, cogemos como base la más pequeña del array
            //02/09/2022 pasamos de 1.111 por defecto a 0.444
            if ($id_supplier == 65 && $combinaciones_peso && min($combinaciones_peso) != 0){
                $product->weight = min($combinaciones_peso);
            } else {
                $product->weight = 0.444;
            }

            //Creamos los productos desactivados por defecto
            $product->active = 0;
            $product->id_manufacturer = $id_fabricante;
            //ponemos por defecto ES standar 21%, para saber su id_tax_rules_group lo tenemos que obtener por BD ya que a veces cambia el id
            $sql_tax_21 = 'SELECT trg.id_tax_rules_group AS id_tax_rules_group
            FROM lafrips_tax_rules_group trg
            JOIN lafrips_tax_rule tar ON tar.id_tax_rules_group = trg.id_tax_rules_group
            JOIN lafrips_tax tax ON tax.id_tax = tar.id_tax
            WHERE trg.active = 1
            AND trg.deleted = 0
            AND tar.id_country = 6 
            AND tax.active = 1
            AND tax.deleted = 0
            AND tax.rate = 21';

            $tax_21 = Db::getInstance()->getValue($sql_tax_21);

            $product->id_tax_rules_group = $tax_21;
            $product->id_supplier = $id_supplier;

            //al ser producto combinaciones, el producto base no tiene ean ni referencia de proveedor
            //$product->ean13 = $ean;
            //$product->supplier_reference = $referencia_proveedor;

            $product->redirect_type = '404';
            $product->advanced_stock_management = 1;

            //Hacemos new product()
            //Si el producto se ha creado, ya dispone de id_product y se pueden añadir categorías, proveedor, almacén, etc
            if($product->add()){       
                //Asignar las categorías (importado)
                $product->updateCategories($product->id_category);

                //Asignar proveedor
                $product_supplier = new ProductSupplier();
                $product_supplier->id_product = $product->id;
                $product_supplier->id_product_attribute = 0;
                $product_supplier->id_supplier = $id_supplier;
                $product_supplier->product_supplier_reference = '';
                $product_supplier->product_supplier_price_te = $precio_proveedor; 
                $product_supplier->id_currency = 1;
                $product_supplier->save();
                

                //Asignar que las cantidades disponibles se basen en gestión avanzada                
                StockAvailable::setProductDependsOnStock((int)$product->id, 1, 1, 0);

                //Creamos el producto con la funcionalidad "Tipo de producto" - "otros" por defecto
                $feature_id = 8;
                // $feature_value_id = 155;
                $feature_value_id = $id_feature_value;
                Product::addFeatureProductImport($product->id, $feature_id, $feature_value_id);

                //por cada combinación en $datos_atributo, creamos en el producto un combinationEntity. Todas con el mismo coste, sin impactos de precio o peso, de momento. newCombinationEntity devuelve el id_product_attribute de la combinación creada
                //si en el campo default ponemos a todos null, parece que luego si no se asigna una combinación como por defecto, salen sin precio, así que a la primera combinación le voy a poner valor 1 (primera vuelta) usando un contador $contador_default
                $contador_default = 1;
                //25/11/2021 Para productos como pet que cada atributo tiene su impacto en peso y precio, hacemos una comparación. Si el array de pesos no contiene todos los pesos iguales, obtenemos min(pesos) que es el peso "base" que hemos puesto al producto, y por cada atributo sacamos la diferencia con dicho peso, siendo ese el impacto. Lo haremos si o no con un marcador generadoaquí dependiendo de si hay diferentes pesos
                $diferentes_pesos = 0;
                if (count(array_unique($combinaciones_peso)) !== 1){
                    //hay más de un peso
                    $diferentes_pesos = 1;
                }
                //lo mismo con el pvp e impacto por combinación si lo hay
                $diferentes_precios = 0;
                if (count(array_unique($combinaciones_pvp)) !== 1){                    
                    //hay más de un precio
                    $diferentes_precios = 1;
                }

                foreach ($datos_atributos AS $atributo){                    
                    if ($contador_default == 1) {
                        $default = 1;
                    } else {
                        $default = null;
                    }
                    $contador_default++;
                    //comprobamos si hay pesos diferentes por atributos y por lo tanto impacto en el peso de la combinación
                    if ($diferentes_pesos) {
                        //hay pesos diferentes, sacamos minimo y comparamos con el de este atributo, la diferencia es el impacto
                        $impacto_peso = round((float)$atributo['peso'] - min($combinaciones_peso), 6);
                    } else {
                        $impacto_peso = 0;
                    }
                    //comprobamos si hay precios diferentes por atributos y por lo tanto impacto en el pvp de la combinación
                    if ($diferentes_precios) {
                        //hay precios diferentes, sacamos minimo y comparamos con el de este atributo, la diferencia es el impacto, pero hay que ponerlo sin iva 21%
                        $impacto_precio_con_iva = (float)$atributo['pvp'] - min($combinaciones_pvp);
                        $impacto_precio = round(($impacto_precio_con_iva/1.21), 4);
                    } else {
                        $impacto_precio = 0;
                    }
                    //tabla lafrips_product_attribute
                    $idProductAttribute = $product->addCombinationEntity(
                        (float)$atributo['precio'], //wholesale price                        
                        $impacto_precio, //price impact                       
                        $impacto_peso, //weight impact
                        0,        //unit_impact
                        null ,    //ecotax
                        (int)0,   //quantity
                        "",       //id_images
                        $atributo['referencia_combinacion'] , //referencia para la combinación
                        $id_supplier, //supplier
                        (int)$atributo['ean'], //ean13 IMPORTANTE asegurarse de que es un número y no string
                        $default, //default 
                        NULL,  //location
                        NULL  //upc 
                    );
                    // die(Tools::jsonEncode(array('error'=> true, 'message'=>$idProductAttribute)));
                    // Al tener el id_product_atribute, le asignamos a la combinación la gestión avanzada (id_product, depends_on_stock, id_shop, id_product_attribute) 
                    StockAvailable::setProductDependsOnStock((int)$product->id, $product->depends_on_stock, 1, (int)$idProductAttribute);
                    //StockAvailable::setProductOutOfStock((int)$product->id, $product->out_of_stock, 1, (int)$idProductAttribute);

                    //a cada combinación (id_product_attribute) le asignamos el supplier, el (int)$idProductAttribute, la referencia de proveedor correspondiente, el coste y el id_currency(1) . El precio metemos por cada atributo por si son diferentes
                    //Tabla lafrips_product_supplier
                    $product->addSupplierReference($id_supplier, (int)$idProductAttribute, $atributo['referencia_proveedor'],(float)$atributo['precio'], 1);          
                    //ahora insertamos el id_attribute que corresponde a cada id_product_attribute
                    Db::getInstance()->execute('
                        INSERT INTO lafrips_product_attribute_combination (`id_attribute`, `id_product_attribute`)
                        VALUES ('.$atributo['id_attribute'].','.$idProductAttribute.')');

                    //Asignar almacén online a cada combinación
                    $product_warehouse = new WarehouseProductLocation();
                    $product_warehouse->id_product = $product->id;
                    $product_warehouse->id_product_attribute = $idProductAttribute;
                    $product_warehouse->id_warehouse = 1;
                    //$product_warehouse->location = ''; 
                    $product_warehouse->save();

                    //07/10/2020 Si el producto es Cerdá, y por tanto Cerdá Adult, introducimos los datos de id_producto, referencia prestashop, etc, en la tabla frik_catalogo_cerda_crear para poder gestionar su disponibilidad de stock automáticamente
                    if ($id_supplier == 65 && $id_fabricante == (int)Manufacturer::getIdByName('Cerdá Adult')) {
                        $sql_update_frik_catalogo_cerda_crear = 'UPDATE frik_catalogo_cerda_crear
                        SET
                        existe_prestashop = 1,
                        referencia_prestashop = "'.$referencia.'",
                        referencia_comb_prestashop = "'.$atributo['referencia_combinacion'].'",
                        id_product_prestashop = '.$product->id.',
                        date_upd = NOW()
                        WHERE ean = "'.$atributo['ean'].'"
                        AND referencia = "'.$atributo['referencia_proveedor'].'"';

                        Db::getInstance()->Execute($sql_update_frik_catalogo_cerda_crear);

                    }    

                    //metemos un log por cada combinación
                    Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                    (operacion, id_product, id_product_attribute, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                    VALUES ('Crear Combinación Importador',".$product->id.", '.$idProductAttribute.','".$atributo['referencia_combinacion']."','".$atributo['ean']."','".$atributo['referencia_proveedor']."',".$id_supplier.",
                            '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");

                }

                //15/12/2021 Con algunos proveedores tenemos problemas a la hora de llevar la imagen a prestashop, no sé si sus servidores han cambiado algo pero por ejemplo con Karactermanía ya no se pueden crear las imágenes. Hago el paso previo de descargarlas a nuestro servidor y luego desde ahí subirlas a prestashop. Hay que utilizar curl, con file_get_contents tampoco acepta. El proceso de bajar la imagen al servidor lo hago en otra función descargaImagen()

                //metemos la imagen, si la acepta, la ponemos como cover, enviamos $cover = false
                $mensaje_imagen = '';                  
                if ($combinaciones_url_imagen_cover){
                    //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                    $combinaciones_url_imagen_cover = $this->descargaImagen($combinaciones_url_imagen_cover);

                    if ($this->gestionImagen($product->id, $combinaciones_url_imagen_cover , true)) {
                        $mensaje_imagen .= ' - Imagen principal subida correctamente';
                    } else {
                        $mensaje_imagen .= ' - LA IMAGEN PRINCIPAL NO PUDO AÑADIRSE';
                    }
                } 
                //el resto de imágenes no son cover, $cover false  
                if ($combinaciones_otras_imagenes) {
                    foreach ($combinaciones_otras_imagenes as $imagen){
                        if ($imagen) {
                            //descargamos la imagen a nuestro servidor y recogemos aquí su url (carpeta download/fotos/nombre_imagen)
                            $imagen = $this->descargaImagen($imagen);

                            if ($this->gestionImagen($product->id, $imagen , false)) {
                                $mensaje_imagen .= ' - Imagen secundaria subida correctamente';
                            } else {
                                $mensaje_imagen .= ' - IMAGEN SECUNDARIA NO PUDO AÑADIRSE';
                            }
                        }                        
                    }
                }     

                //Añadimos el log del producto global a frik_log_import_catalogos. Al tener combinaciones, saldrán primero las combinaciones con su ref de proveedor, etc y despues esta, que será el producto en general 
                Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                     (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                     VALUES ('Crear Producto Combinaciones Importador',".$product->id.",'".$referencia."','','Producto Combinaciones',".$id_supplier.",
                            '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");
                
            }

        }

        if($response)
            die(Tools::jsonEncode(array('message'=>'Producto creado correctamente, recuerda revisar su IVA y su peso'.$mensaje_imagen)));

    }


    /*
    * Función que busca en la BD si la referencia introducida para un nuevo producto existe ya para otro producto
    *
    */
    public function ajaxProcessComprobarReferencia(){        
        //asignamos a $referencia el valor introducido por el usuario al crear el producto        
        $referencia = Tools::getValue('referencia',0);
        if(!$referencia)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se encuentra el producto.')));

        $response = true;
        //Buscamos la referencia en lafrips_product
        $busca_ref = 'SELECT id_product FROM lafrips_product WHERE reference = "'.$referencia.'";';

        $existe_referencia = Db::getInstance()->executeS($busca_ref);


        if($existe_referencia)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Referencia duplicada')));


        if($response)
            die(Tools::jsonEncode(array('message'=>'La referencia es correcta')));
    }

    /*
    * Función que busca en la BD si un ean existe ya en un producto o atributo
    *
    */
    public function ajaxProcessComprobarEan(){        
        //asignamos a $ean el valor introducido por el usuario al crear el producto        
        $ean = Tools::getValue('ean_introducido',0);
        if(!$ean)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error con el ean.')));

        $response = true;
        //Buscamos el ean en lafrips_product y lafrips_product_attribute
        $buscar_ean = 'SELECT pro.reference AS referencia_ean, pat.reference AS referencia_atributo, IF(pat.ean13, 1, 0) AS atributo
        FROM lafrips_product pro
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = pro.id_product
        WHERE pat.ean13 = "'.$ean.'"
        OR pro.ean13 = "'.$ean.'";';        

        $existe_ean = Db::getInstance()->executeS($buscar_ean);


        if($existe_ean)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Ean duplicado', 'duplicado'=> $existe_ean)));


        if($response)
            die(Tools::jsonEncode(array('message'=>'El ean no está duplicado')));
    }

    /*
    * Función que quitará permitir pedidos a todos los productos salvo los que tengan la categoría Prepedido, se le llama desde configuration
    *
    */
    public function ajaxProcessResetearPermitir(){
        //Cambiamos a no permitir pedido a todos los productos salvo los que tengan la categoría Prepedido - 121
        $sql_no_permitir = 'UPDATE lafrips_stock_available SET out_of_stock = 2 WHERE id_product NOT IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 121);';
        Db::getInstance()->Execute($sql_no_permitir);


        $id_empleado = Context::getContext()->employee->id;
        $nombre_empleado = Context::getContext()->employee->firstname;

        //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos                
        Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                     (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                     VALUES ('RESETEAR PERMITIR PEDIDOS',0,'RESETEAR PERMITIR PEDIDOS','0','RESETEAR PERMITIR PEDIDOS',0,
                            '0',".$id_empleado.",'".$nombre_empleado."',  NOW());");           
    }


    //prueba de console.log para php
    // public function console_log($output, $with_script_tags = true) {
    //     $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
    //     if ($with_script_tags) {
    //         $js_code = '<script>' . $js_code . '</script>';
    //     }
    //     echo $js_code;
    // }

    //15/12/2021 Función que descarga a nuestro servidor la imagen desde la url remota
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
                            
                // $this->confirmations[] = $this->l('Imágenes subidas correctamente');
                return true;
            }else{                
                $image->delete();
                //$this->errors[] = Tools::displayError('Error generando la imagen de la url: '.$url);
                return false;
            }   
        } else {
            return false;
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

            //MOD: 25/05/2023 Si enviamos los parámetros como los usa esta función sacada de AdminImportController parece que mete un marco blanco a las fotos, pero mirando en AdminProductsController en su función copyImage() se ve que solo envía hasta $file_type y no mete ese marco, parece que funciona

            // ImageManager::resize($tmpfile, $path.'.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5,
            //                     $src_width, $src_height);
            ImageManager::resize($tmpfile, $path.'.jpg', null, null, 'jpg');
            $images_types = ImageType::getImagesTypes($entity, true);

            if ($regenerate) {
                $previous_path = null;
                $path_infos = array();
                $path_infos[] = array($tgt_width, $tgt_height, $path.'.jpg');
                foreach ($images_types as $image_type) {
                    //en AdminImportcontroller aquí utiliza self::get_best_path pero como no estamos en ese controlador, he traido la función get_best_path() justo debajo de esta
                    $tmpfile = $this->get_best_path($image_type['width'], $image_type['height'], $path_infos);

                    //MOD: 25/05/2023 Si enviamos los parámetros como los usa esta función sacada de AdminImportController parece que mete un marco blanco a las fotos, pero mirando en AdminProductsController en su función copyImage() se ve que solo envía hasta $file_type y no mete ese marco, parece que funciona

                    // if (ImageManager::resize($tmpfile, $path.'-'.stripslashes($image_type['name']).'.jpg', $image_type['width'],
                    //                     $image_type['height'], 'jpg', false, $error, $tgt_width, $tgt_height, 5,
                    //                     $src_width, $src_height)) {
                    if (ImageManager::resize($tmpfile, $path.'-'.stripslashes($image_type['name']).'.jpg', $image_type['width'],
                        $image_type['height'], 'jpg')) {
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
    * Función que busca los grupos de atributos. Este grupo se mostrará en un select en el dialog de crear productos con atributos
    *
    */
    public function ajaxProcessGruposAtributos(){        
        //sacamos los grupos de atributos
        $attribute_group = AttributeGroup::getAttributesGroups(1);
        if(!$attribute_group) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error accediendo a los grupos de atributos.')));
        }
        //introducir un campo sin grupo de atributos al Select de grupos de atributos
        $grupo_vacio = array(array('id_attribute_group'=> 0, 'name'=> 'Selecciona Tipo'));
        $attribute_group = array_merge($grupo_vacio, $attribute_group);        

        //si se reciben los grupos de atributos, los enviamos de vuelta en la variable lista_grupos_atributos
        if($attribute_group)
            die(Tools::jsonEncode(array('message'=>'bien por ahora', 'lista_grupos_atributos' => $attribute_group)));
    }


    /*
    * Función que busca los atributos correspondientes a un grupo de atributos. Este grupo se selecciona en el dialog box de importador de productos, y por ajax pedimos los atributos que corresponden
    *
    */
    public function ajaxProcessAtributosGrupo(){        
        //asignamos a $id_attribute_group el id del select seleccionado por el usuario al escoger grupo de atributos y que hemos metido en dataObj, que es el data que llega por ajax  
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

    /*
    * Función que devuelve datos sobre las líneas de producto seleccionadas para crear un producto con combinaciones
    *
    */
    public function ajaxProcessInfoAtributosSeleccionados(){
        $response = true;
        
        $ids_atributos = Tools::getValue('ids', 0);
        if(!$ids_atributos)
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se encuentran los atributos.')));

        //convertimos $ids_atributos para poder usarlo en una sql        
        $lista_ids = implode(",", $ids_atributos);

        // die(Tools::jsonEncode(array('error'=> true, 'message'=>$lista_ids)));
        //Obtenemos los datos almacenados de cada producto por proveedor
        $sql_productos = 'SELECT id_import_catalogos, ean, referencia_proveedor, atributo
                            FROM frik_import_catalogos WHERE id_import_catalogos IN ('.$lista_ids.')';

        if ($datos_combinaciones = Db::getInstance()->executeS($sql_productos)) {
            die(Tools::jsonEncode(array('message'=>'Recogidos datos del artículo', 'datos_combinaciones' => $datos_combinaciones)));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se puede acceder a los datos de las combinaciones.')));
        }

    }

    /*
    * 14/09/2021 función que recibe uno o dos parámetros, el nombre en español y si hay en inflés, para generar el array de lenguaje para crear el producto en función de los idiomas en Prestashop. Para portugués se pone español también
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

      /*
    * 29/10/2021 Función que busca en la BD si la referencia introducida para copiar datos de un producto existe y si es así devuelve sus categorías y nombre/descripción, seo y tipo
    * DUPLICADA DE AdminCrearProductos, modificamos para que envíe el id_product y no hace falta descripciones etc. Esos datos los obtenemos con otra función específica mientras se crea el producto
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

            //obtenemos las categorías por id , para almacenarlas en un input hidden en el front con javascript, y también sacamos los nombres para que el usuario vea que categorías son. Para evitar categorías como amazon, disponible recatalogar, etc, hacemos una select utilizando nleft nright, incluyendo solo lo de dentro de La Frikileriakids 2232 y Para mayores 2555 (2551 test), luego añadiremos Importador por defecto?. Quitamso la categoría de precio, que la calcularemos al crear el producto según su precio.
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

            // //la descripción, nombre y seo solo en español
            // $sql_descripciones = 'SELECT name, description_short, meta_description, meta_title  FROM lafrips_product_lang WHERE id_lang = 1 AND id_product = '.$id_product;
            // $descripciones = Db::getInstance()->executeS($sql_descripciones)[0];
            // //tipo de producto 
            // $sql_feature_value = 'SELECT id_feature_value FROM lafrips_feature_product WHERE id_feature = 8 AND id_product = '.$id_product;
            // $feature_value = Db::getInstance()->executeS($sql_feature_value)[0];
            
            //solo el nombre, solo en español
            $nombre_producto = Product::getProductName($id_product, 0, 1);
            
            die(Tools::jsonEncode(array(
                'message' => 'La referencia es correcta', 
                'category_default' => $category_default, 
                'ids_categorias' => $ids_categorias, 
                'categorias' => $categorias, 
                'id_producto_copiar' => $id_product,
                'nombre_producto' => $nombre_producto
            )));
        }
            
    }

    //función para obtener el mensaje de disponibilidad available_later según el proveedor. Tiene en cuenta id_lang para España, Portugal y el resto, que sería todos inglés
    //devuelve un array, en su primera posición está el array para available_now y en la segunda el array para available_later
    public function mensajeAvailable($id_supplier) {
        //buscamos los mensajes en lafrips_mensaje_disponibilidad para ese id_supplier. Si no encuentra el supplier obtiene el mensaje por defecto, por cada id_lang.
        //comprobamos que id_supplier está en la tabla, si no sacamos los valores default
        if (Db::getInstance()->getValue("SELECT id_mensaje_disponibilidad FROM lafrips_mensaje_disponibilidad WHERE is_default = 0 AND id_supplier = $id_supplier")) {
            $sql_mensajes = "SELECT id_lang, available_now, available_later 
            FROM lafrips_mensaje_disponibilidad
            WHERE id_supplier = $id_supplier";
        } else {
            $sql_mensajes = "SELECT id_lang, available_now, available_later 
            FROM lafrips_mensaje_disponibilidad
            WHERE is_default = 1";
        }

        if ($mensajes = Db::getInstance()->ExecuteS($sql_mensajes)) {
            foreach ($mensajes AS $mensaje) {
                if ($mensaje['id_lang'] == 1) {
                    $available_now_es = $mensaje['available_now'];
                    $available_later_es = $mensaje['available_later'];
                } elseif ($mensaje['id_lang'] == 18) {
                    $available_now_pt = $mensaje['available_now'];
                    $available_later_pt = $mensaje['available_later'];
                } else {
                    $available_now_en = $mensaje['available_now'];
                    $available_later_en = $mensaje['available_later'];
                }
            }
            //generamos el array de idiomas para available now y later
            $idiomas = Language::getLanguages();
        
            $available_later_todos = array();
            foreach ($idiomas as $idioma) {
                if ($idioma['iso_code'] == 'es') {
                    $available_now_todos[$idioma['id_lang']] = $available_now_es;  
                    $available_later_todos[$idioma['id_lang']] = $available_later_es;     
                } elseif ($idioma['iso_code'] == 'pt') {
                    $available_now_todos[$idioma['id_lang']] = $available_now_pt;
                    $available_later_todos[$idioma['id_lang']] = $available_later_pt;     
                } else {
                    $available_now_todos[$idioma['id_lang']] = $available_now_en;
                    $available_later_todos[$idioma['id_lang']] = $available_later_en;   
                }                
            }

            return array($available_now_todos, $available_later_todos);

        } else {
            return false;
        }
        
    }


}
