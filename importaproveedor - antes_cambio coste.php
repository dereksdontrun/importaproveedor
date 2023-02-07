<?php
/**
 * Importador de productos desde catálogo de proveedor
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Importaproveedor extends Module
{
    protected $config_form = false;
    protected $admin_tab = array();    

    public function __construct()
    {
        $this->name = 'importaproveedor';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'Sergio™';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;
		
        parent::__construct();
        
        //tab para enviar al formulario de búsqueda e importación de productos
        $this->admin_tab[] = array('classname' => 'AdminImportaProveedor', 'parent' => 'AdminCatalog', 'displayname' => 'Importar Productos Proveedores');
        //tab para ir al formulario para crear productos sin importar datos
        $this->admin_tab[] = array('classname' => 'AdminCrearProductos', 'parent' => 'AdminCatalog', 'displayname' => 'Crear Productos');

        $this->displayName = $this->l('Importar productos de proveedor');
        $this->description = $this->l('Importar productos desde el catálogo de proveedor');

        $this->confirmUninstall = $this->l('Are you sure you want to delete this module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }


    public function install()
    {        
        include(dirname(__FILE__).'/sql/install.php');

        //metemos en lafrips_configuration la configuración inicial de que los productos no se pongan Permitir pedido automáticamente
        Configuration::updateValue('IMPORTA_PROVEEDOR_PERMITIR_PEDIDO', false);
        Configuration::updateValue('IMPORTA_PROVEEDOR_PRECIO_LIMITE', '');
        Configuration::updateValue('IMPORTA_PROVEEDOR_ANTIGUEDAD', '');
        Configuration::updateValue('IMPORTA_PROVEEDOR_PROVEEDORES', '');

        foreach ($this->admin_tab as $tab)
            $this->installTab($tab['classname'], $tab['parent'], $this->name, $tab['displayname']);

        //añadimos el hook actionUpdateQuantity que se activa cuando cambia la cantidad disponible de un producto en lafrips_stock_available
        return parent::install() &&
            $this->registerHook('actionUpdateQuantity') &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        Configuration::deleteByName('IMPORTA_PROVEEDOR_PERMITIR_PEDIDO');
        Configuration::deleteByName('IMPORTA_PROVEEDOR_PRECIO_LIMITE');
        Configuration::deleteByName('IMPORTA_PROVEEDOR_ANTIGUEDAD');
        Configuration::deleteByName('IMPORTA_PROVEEDOR_PROVEEDORES');

        foreach ($this->admin_tab as $tab)
            $this->unInstallTab($tab['classname']);

        return parent::uninstall();
    }

    /*
     * create menu tab
     */
    protected function installTab($classname = false, $parent = false, $module = false, $displayname = false) {
        if (!$classname)
            return true;

        $tab = new Tab();
        $tab->class_name = $classname;
        if ($parent)
            if (!is_int($parent))
                $tab->id_parent = (int) Tab::getIdFromClassName($parent);
            else
                $tab->id_parent = (int) $parent;
        if (!$module)
            $module = $this->name;
        $tab->module = $module;
        $tab->active = true;
        if (!$displayname)
            $displayname = $this->displayName;
        $tab->name[(int) (Configuration::get('PS_LANG_DEFAULT'))] = $displayname;

        if (!$tab->add())
            return false;

        return true;
    }

    protected function unInstallTab($classname = false) {
        if (!$classname)
            return true;

        $idTab = Tab::getIdFromClassName($classname);
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
            ;
        }
        return true;
    }    

    //función hook action para cuando cambia el stock disponible de un producto, se comprobará si que da sin stock, si no tiene atributos, y si está disponible en la tabla de import_catalogos, para marcar permitir pedidos
    // Located in: /classes/stock/StockAvailable.php
    // Parameters:
    // array(
        // 'id_product' => (int) Product ID,
        // 'id_product_attribute' => (int) Product attribute ID,
        // 'quantity' => (int) New product quantity
    // );
    public function hookActionUpdateQuantity($params)
    {
        //Comprobamos la configuración del módulo, si está activa la automatización de productos
        if (!Configuration::get('IMPORTA_PROVEEDOR_PERMITIR_PEDIDO')){
            return;
        }
        //de momento solo trabajaremos sobre productos sin atributos, y además, el nuevo stock disponible, quantity, debe ser 0 
        if ($params && $params['id_product'] !== 0 && $params['id_product_attribute'] == 0 && $params['quantity'] == 0 ){      
            //sacamos la info de params, id_product, ya que por las condiciones del if, no debe ser atributo y el nuevo stock disponible será 0
            $id_product = $params['id_product'];

            //NOOO - hay que asegurarse de que el hook ha funcionado porque el movimiento de stock ha sido producido por una compra de cliente o porque un empleado ha modificado el stock pasándolo a 0. Esto es porque este hook también se activa si, por ejemplo, creamos un nuevo producto, ya que aparece en la tabla stock_available con quantity = 0, o  incluso si manualmente cambiamos en la ficha de producto Permitir pedidos y guardamos, ya que parece interpretar que es un cambio de quantity a 0 al guardar out_of_stock = lo que sea
            //buscamos el id de tipo de movimiento del último movimiento de stock del producto, y si este es 2: disminuir, 3: pedido de cliente, 4: regulación de inventario disminuir, 9:compra de empleado u 11:restar desde módulo localización , lo consideramos válido (no consideramos atributos):
            // $sql_ultimo_movimiento = 'SELECT smv.id_stock_mvt_reason AS id_movimiento
            // FROM lafrips_stock_mvt smv
            // INNER JOIN lafrips_stock sto ON smv.id_stock = sto.id_stock							
            // WHERE sto.id_product = '.$id_product.'
            // AND sto.id_product_attribute = 0
            // GROUP BY smv.id_stock_mvt   
            // ORDER BY smv.id_stock_mvt DESC
            // LIMIT 1;';

            // $ultimo_movimiento = Db::getInstance()->executeS($sql_ultimo_movimiento);
            // $id_ultimo_movimiento = (int)$ultimo_movimiento[0]['id_movimiento'];
            // if ($id_ultimo_movimiento !== 2 && $id_ultimo_movimiento !== 3 && $id_ultimo_movimiento !== 4 && $id_ultimo_movimiento !== 9 && $id_ultimo_movimiento !== 11){
            //     return;
            // }

            // if (!in_array($id_ultimo_movimiento, array(2,3,4,9,11))){
            //     return;
            // }

           
            if ($producto = new Product($id_product)){                
                //echo '<br>'.Product::getProductName($id_product);
                //comprobamos que esté en gestión avanzada
                if (!StockAvailableCore::dependsOnStock($id_product)){
                    return;
                }        
                //comprobamos que no tenga ya marcado out_of_stock (permitir pedido = 1)
                if (StockAvailableCore::outOfStock($id_product) == 1){
                    return;
                }
                //comprobamos que no tenga marcada la categoría 2440 - No Permitir Pedido o la categoría Prepedidos - 121
                $categorias = Product::getProductCategories($id_product);
                $cats_buscar = array(2440, 121);
                // if (in_array(2440, $categorias) || in_array(121, $categorias)){
                if (count(array_intersect($cats_buscar, $categorias)) > 0){
                    return;
                }
                //si el producto está desactivado, salimos
                if (!$producto->active){
                    return;
                }
                //nos aseguramos de que el producto no tiene atributos
                if (Product::getProductAttributesIds($id_product)){
                    return;
                }
                //si el producto es pack nativo, salimos
                if ($producto->cache_is_pack){
                    return;
                }

                //si el producto es Cerdá Kids salimos, ya que tiene su propio proceso para gestionar la disponibilidad de stock desde al archivo que nos suben cada hora 
                //07/10/2020 A partir de ahora todos los productos Cerdá se gestionarán con su propio catálogo cada hora, de modo que ignoramos cualquier producto con fabricante Cerdá Kids o Cerdá Adult. Puede haber otros con supplier Cerdá, que en principio ya estarán descatalogados o serán de Karactermanía u otros               
                if (($producto->id_manufacturer == (int)Manufacturer::getIdByName('Cerdá Kids')) || ($producto->id_manufacturer == (int)Manufacturer::getIdByName('Cerdá Adult'))) {
                    return;
                }

                //si el producto es un pack del módulo advancedpacks, salimos  
                //desinstalado módulo      
                // $sql_advanced_pack = 'SELECT id_pack FROM lafrips_pm_advancedpack WHERE id_pack = '.$id_product;
                // $advanced_packs = Db::getInstance()->executeS($sql_advanced_pack);
                // if ($advanced_packs){
                //     return;
                // }         
                
                //Comprobamos la configuración del módulo, sacamos la antigüedad del producto y su pvp y lo comparamos con los valores de configuración
                if (Configuration::get('IMPORTA_PROVEEDOR_PRECIO_LIMITE') !== null){
                    $pvp_limite = Configuration::get('IMPORTA_PROVEEDOR_PRECIO_LIMITE');
                }else{
                    $pvp_limite = 0;
                }

                if (Configuration::get('IMPORTA_PROVEEDOR_ANTIGUEDAD') !== null){
                    $antiguedad_limite = Configuration::get('IMPORTA_PROVEEDOR_ANTIGUEDAD');
                }else{
                    $antiguedad_limite = 0;
                }
                
                //sacamos el pvp sin descuentos del producto:
                $pvp_sin_descuento = $producto->getPriceWithoutReduct();
                //sacamos antigüedad en días del producto, date('Y-m-d H:i:s') equivale a NOW():
                $diff = date_diff(date_create($producto->date_add), date_create(date('Y-m-d H:i:s')));
                $antiguedad = $diff->format('%a');
                //no aplicar a productos más antiguos de antiguedad_limite y de menos valor que pvp_limite
                if (($antiguedad_limite > 0) && ($antiguedad > $antiguedad_limite) && ($pvp_sin_descuento < $pvp_limite)){
                    return;
                }
                //si antiguedad_limite es 0 pero pvp_limite no, no aplicar a los de pvp inferior a pvp_limite
                if (($antiguedad_limite == 0) && ($pvp_limite !== 0) && ($pvp_sin_descuento < $pvp_limite)){
                    return;
                }
                //si pvp_limite es 0 pero antiguedad_limite no, no aplicar a los que son más antiguos que antiguedad_limite 
                if (($antiguedad_limite !== 0) && ($pvp_limite == 0) && ($antiguedad > $antiguedad_limite)){
                    return;
                }                
                
                // $proveedores_tabla = array();                
                // //sacamos los proveedores que tenemos en tabla frik_import_catalogos
                // $sql_proveedores_tabla_import = 'SELECT DISTINCT id_proveedor FROM frik_import_catalogos;';
                // $lista_proveedores_tabla = Db::getInstance()->executeS($sql_proveedores_tabla_import);
                // foreach ($lista_proveedores_tabla as $proveedor){
                //     $proveedores_tabla[] = $proveedor['id_proveedor'];
                // }

                // 08/06/2020 modificación para usar solo los proveedores configurados para admitir automatización
                //sacamos de configuración los proveedores para los que hemos activado la automatización
                $proveedores_permitir_json = Configuration::get('IMPORTA_PROVEEDOR_PROVEEDORES');
                $proveedores_tabla = json_decode($proveedores_permitir_json);
                
                $proveedores_producto = array();
                $info_proveedores_producto = array();        
                //sacamos los proveedores que tiene asignados, sacando el objeto prestashop collection y extrayendo los suppliers
                $collection_proveedores_producto = ProductSupplier::getSupplierCollection($id_product);                  
                foreach ($collection_proveedores_producto as $associated_supplier){            
                    $proveedores_producto[] = $associated_supplier->id_supplier;  
                    $info_proveedores_producto[$associated_supplier->id_supplier]['product_supplier_reference'] = $associated_supplier->product_supplier_reference;
                    $info_proveedores_producto[$associated_supplier->id_supplier]['product_supplier_price_te'] = $associated_supplier->product_supplier_price_te;
                    $info_proveedores_producto[$associated_supplier->id_supplier]['id_currency'] = $associated_supplier->id_currency;                  
                }
           
                //comparamos los arrays de ids de proveedor y almacenamos en otro las coincidencias:
                $proveedores_disponibles = array_intersect($proveedores_tabla , $proveedores_producto);
                
                //si el producto no tiene asignados proveedores de la tabla import_catalogos, salimos del proceso
                if (empty($proveedores_disponibles)){          
                    return;
                }
                
                //por cada proveedor disponible en la tabla, con disponibilidad 1 (disponible), sacamos el precio para este producto por su referencia de proveedor. Ponemos LIMIT 1 porque a veces hay productos con la misma referencia de proveedor, que están en mal estado, etc        
                foreach ($proveedores_disponibles as $proveedor){
                    $sql_info_tabla = 'SELECT id_import_catalogos, precio FROM frik_import_catalogos 
                    WHERE disponibilidad = 1
                    AND id_proveedor = '.$proveedor.' AND referencia_proveedor = "'.$info_proveedores_producto[$proveedor]['product_supplier_reference'].'" 
                    ORDER BY precio DESC LIMIT 1';
        
                    $info_proveedor_tabla = Db::getInstance()->executeS($sql_info_tabla);
                    //si se ha encontrado un proveedor con la referencia de proveedor del producto
                    if ($info_proveedor_tabla){
                        //si se ha encontrado precio
                        if ($info_proveedor_tabla[0]['precio']){
                            //guardamos en $precios_tabla el proveedor como key y el precio como value
                            $precios_tabla[$proveedor] = $info_proveedor_tabla[0]['precio'];   
                        }
                    }         
                }        
                
                //si no hay nada en $precios_tabla, es decir, no se ha encontrado proveedor en la tabla que coincida la referencia de proveedor y esté disponible, salimos
                if (!$precios_tabla){
                    return;
                }

                //ordenamos array por value de menor a mayor
                asort($precios_tabla);        
                //sacamos el proveedor disponible más barato para el producto 
                //array_key_first() no existe antes de php 7.3 - hacemos un foreach
                //$id_proveedor_permitir = array_key_first($precios_tabla);
                foreach ($precios_tabla as $key => $value){
                    $id_proveedor_permitir = $key;
                    //salimos del foreach en la primera vuelta, con lo que tenemos el primer key
                    break;
                }
                        
                //una vez escogido el proveedor que se va a asignar como default para el producto (si no lo es) comparamos el precio de proveedor con el que tiene el producto actualmente para ese proveedor, si no coincide lo actualizamos, de momento en lafrips_product wholesale_price no, por si el producto es para cajas y se ha reducido
                //11/11/2021 Para evitar rectalogar productos con coste bajo por haber estado en cajas, a partir de ahora metemos el nuevo coste también en wholesale_price. Antes de pisar el coste existente con el nuevo, comprobamos el coste anterior, almacenamos el cambio en una tabla, y si el porcentaje de diferencia es 20% o más se avisa a Alberto (en un proceso posterior) para que modifique el pvp a su gusto. El porcentaje sería ((coste_final - coste_inicial)/coste_inicial)*100
                $coste_inicial = $producto->wholesale_price;
                $coste_final = $precios_tabla[$id_proveedor_permitir];
                $variacion = (($coste_final - $coste_inicial)/$coste_inicial)*100;
                //actualizamos coste
                $producto->wholesale_price = $coste_final;
                //guardamos en tabla log              
                Db::getInstance()->Execute("INSERT INTO frik_log_cambio_coste_permitir_pedido
                (id_product, product_reference, product_supplier_reference, old_wholesale_price, new_wholesale_price, porcentaje_variacion, id_supplier, supplier_name, date_add)
                VALUES
                (".$id_product.",'".$producto->reference."','".$info_proveedores_producto[$id_proveedor_permitir]['product_supplier_reference']."',".$coste_inicial.",".$coste_final.",".$variacion.",".$id_proveedor_permitir.",'".Supplier::getNameById($id_proveedor_permitir)."', NOW())"); 

                //además, hay que comprobar si el producto que se agota está en outlet y tiene descuentos, en cuyo caso le quitamos la categoría (nuevos outlet y para cajas sorpresa también si están) y eliminamos los descuentos (guardamos el id_product y descuento en frik_productos_outlet)
                if (in_array(319, $categorias)) {
                    Db::getInstance()->Execute("DELETE FROM lafrips_category_product WHERE id_category IN ( 319, 2344, 2420) AND id_product = $id_product");

                    //averiguamos si el producto tiene descuento del tipo outlet para eliminarlo y guardarlo
                    $sql_descuento = "SELECT id_specific_price, reduction FROM lafrips_specific_price 
                    WHERE id_specific_price_rule = 0
                    AND id_customer = 0 
                    AND id_group = 0
                    AND lafrips_specific_price.to = '0000-00-00 00:00:00'
                    AND reduction_type = 'percentage' 
                    AND reduction >= 0.2       
                    AND id_product = $id_product";
                    $resultado_descuento = Db::getInstance()->ExecuteS($sql_descuento);

                    if ($resultado_descuento) {                        
                        $reduction = $resultado_descuento[0]['reduction'];
                        $id_precio_especifico = $resultado_descuento[0]['id_specific_price'];
                        $sql = "INSERT INTO frik_productos_outlet (id_product, reduction, date_add) VALUES ('".$id_product."', ".$reduction.", NOW());";
                        Db::getInstance()->Execute($sql);
                        //borramos descuento
                        Db::getInstance()->Execute("DELETE FROM lafrips_specific_price WHERE id_specific_price = ".$id_precio_especifico);
                        
                    } 
                }           

                //si el coste cambia en proveedor, lo actualizamos
                if ($precios_tabla[$id_proveedor_permitir] !== $info_proveedores_producto[$id_proveedor_permitir]['product_supplier_price_te']){
                    //actualizamos el precio de proveedor instanciando product supplier, primero obteniendo el id de la tabla product_supplier (0 es id_product_attribute)
                    $id_product_supplier = ProductSupplier::getIdByProductAndSupplier($id_product,0,$id_proveedor_permitir);
                    $product_supplier = new ProductSupplier($id_product_supplier);                    
                    $product_supplier->product_supplier_price_te = $precios_tabla[$id_proveedor_permitir];
                    $product_supplier->save();                   
                    
                }
        
                //Si el producto está descatalogado (id_category_default = 89) y tiene la categoría Disponible recatalogar (id 2184) marcada, le marcamos la categoría Recatalogar (id 2179), si no tiene la categoría disponible recatalogar lo dejamos como está
                if ($producto->id_category_default == 89){

                    //comprobamos que tenga la categoría disponible recatalogar 2184 y que no tenga ya recatalogar 2179                    
                    if (in_array(2184, $categorias) && !in_array(2179, $categorias)){
                        //marcamos recatalogar
                        $producto->addToCategories([2179]);                        
        
                        //introducir el id_product, el proceso (recatalogar_importador) y la fecha en la tabla log frik_log_descatalogar
                        //22/12/2020 metemos también el id_employee 44 de automatizador
                        $id_employee_automatico = 44;
                        Db::getInstance()->Execute("INSERT INTO frik_log_descatalogar (id_product, proceso, id_employee, fecha) VALUES ('".$idProd."', 'marcado_recatalogar_importador_automatico',".$id_employee_automatico.", NOW());");
                        //Db::getInstance()->Execute("INSERT INTO frik_log_descatalogar (id_product, proceso, fecha) VALUES ('".$id_product."', 'marcado_recatalogar_importador_automatico', NOW());");  
                    
                    }
                }
        
                //Ponemos el mensaje de prepedido al producto
                //mensaje prepedido. Si es de Cerdá Kids ponemos otro
                //id de manufacturer by name
                // $id_manufacturer_cerda_kids = (int)Manufacturer::getIdByName('Cerdá Kids');
                // if ($producto->id_manufacturer == $id_manufacturer_cerda_kids) {
                //     $available_later = 'Atención: el plazo de envío de este artículo es de tres a seis días.';
                // } else {
                //     $available_later = 'Atención: el plazo de envío de este artículo es de una semana a diez días.';
                // }

                //10/01/2022 Si el proveedor que ponemos es Difuzed, ponemos otro mensaje
                if ($id_proveedor_permitir == 7) {
                    $available_later = 'Atención: el plazo de envío de este artículo es de hasta tres semanas.';
                } else {
                    $available_later = 'Atención: el plazo de envío de este artículo es de una semana a diez días.';
                }

                //generamos elarray de idiomas para available later
                $idiomas = Language::getLanguages();
    
                $available_later_todos = array();
                foreach ($idiomas as $idioma) {                    
                    $available_later_todos[$idioma['id_lang']] = $available_later;                    
                }

                $producto->available_later = $available_later_todos;    

                // $available_later = 'Atención: el plazo de envío de este artículo es de una semana a diez días.';                
                // $producto->available_later = [1 => $available_later, 11 => $available_later, 12 => $available_later, 13 => $available_later, 14 => $available_later];
        
                //si el proveedor por defecto no es el que vamos a asignar, se lo ponemos
                if ($producto->id_supplier !== $id_proveedor_permitir){                    
                    $producto->id_supplier = $id_proveedor_permitir;
                }
        
                //Marcamos permitir pedidos (0 no permitir, 1 permitir, 2 por defecto)
                StockAvailable::setProductOutOfStock($id_product, 1);
        
                //comprobamos que el producto este disponible para la venta available_for_order=1 , lo hace para la tabla product_shop también
                if (!$producto->available_for_order) {
                    $producto->available_for_order = 1;
                }        
        
                //guardamos los cambios en producto
                $producto->save();
                
                //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
                $id_empleado = 0;
                $nombre_empleado = 'Permitir_automático';
                $referencia_producto = $producto->reference;
                $ean = $producto->ean13;
                $referencia_proveedor = $info_proveedores_producto[$id_proveedor_permitir]['product_supplier_reference'];
                $id_proveedor = $id_proveedor_permitir;
                $nombre_proveedor = Supplier::getNameById($id_proveedor);
                Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                     (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                     VALUES ('Poner Permitir Pedidos - Proceso Automático',".$id_product.",'".$referencia_producto."','".$ean."','".$referencia_proveedor."',".$id_proveedor.",
                            '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");       

                //debug para exportar a un txt lo hecho en pruebas  
                // $realizado = 'Trabajo sobre producto ID = '.$id_product.' '.Product::getProductName($id_product).' pasado a permitir pedido con proveedor ID '.$id_proveedor_permitir.' y precio '.$precios_tabla[$id_proveedor_permitir];

                // //debug - quitar 
                // file_put_contents('/var/www/vhost/lafrikileria.com/home/html/tmpAuxiliar/pruebas_hook_'.date('dmY_h:i:s').'.txt', print_r($realizado, true));

            } else {
                return;
            } 
            

            return;
        }

        //si el cambio en stock implica que quantity > 0 comprobamos si es categoría Prepedido, productos especiales (88), virtuales, cajas sorpresa (2343), si no y tiene marcado permitir pedidos lo quitamos, ya que no queremos productos con stock y con permitir pedidos.
        //en el caso de Cerdá kids y Adult, si se diera por error que tenga stock físico, también queremos quitarselo, de modo que no los distinguimos aquí.
        //********************* Recordar quitar la parte que corresponde de la tarea cron que hace esto ahora: no_disponible_sin_stock_online.php
        if ($params && $params['id_product'] !== 0 && $params['id_product_attribute'] == 0 && $params['quantity'] > 0 ){
            $id_product = $params['id_product'];
            //instanciamos el producto
            if ($producto = new Product($id_product)){  
                //comprobamos que esté en gestión avanzada
                if (!StockAvailableCore::dependsOnStock($id_product)){
                    return;
                }       
                //comprobamos que no tenga marcada la categoría Prepedidos - 121, Cajas Sorpresa - 2343, Prod Especial - 88, Permitir pedidos con stock - 2386
                $categorias = Product::getProductCategories($id_product); 
                $cats_buscar = array(2343, 88, 121, 2386);                
                if (count(array_intersect($cats_buscar, $categorias)) > 0){               
                    return;
                } 
                //si el producto es virtual, salimos
                if ($producto->is_virtual){
                    return;
                }
                //si el producto es un pack del módulo advancedpacks, salimos 
                //módulo desinstalado       
                // $sql_advanced_pack = 'SELECT id_pack FROM lafrips_pm_advancedpack WHERE id_pack = '.$id_product;
                // $advanced_packs = Db::getInstance()->executeS($sql_advanced_pack);
                // if ($advanced_packs){
                //     return;
                // }      

                //comprobamos que tenga ya marcado out_of_stock (permitir pedido = 1), si lo tiene, no siendo Prepedido, y dado que tiene stock available > 0, se lo quitamos, poniendo "por defecto"
                if (StockAvailableCore::outOfStock($id_product) == 1){
                    StockAvailable::setProductOutOfStock($id_product, 2);
                } else {
                    return;
                }  
                
                //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
                $id_empleado = 0;
                $nombre_empleado = 'Permitir_automático';
                $referencia_producto = $producto->reference;
                $ean = $producto->ean13;
                $referencia_proveedor = '';
                $id_proveedor = '';
                $nombre_proveedor = '';
                Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                     (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                     VALUES ('Quitar Permitir - Tiene Stock - Proceso Automático',".$id_product.",'".$referencia_producto."','".$ean."','','',
                            '',".$id_empleado.",'".$nombre_empleado."',  NOW());");

            } else {
                return;
            } 

            return;
        }
    }

    //Probando para añadir página de configuración - 15/11/2019
    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        // if (((bool)Tools::isSubmit('submitImportaproveedorModule')) == true) {
        //     $this->postProcessConfiguracion();            
        // }
        // if (((bool)Tools::isSubmit('submitCatalogos')) == true) {
        //     $this->postProcessCatalogo();            
        // }

        // $this->context->smarty->assign('module_dir', $this->_path);

        // $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        // return $output.$this->renderForm();


        $output = null;

        $this->context->smarty->assign('module_dir', $this->_path);

        if (((bool)Tools::isSubmit('submitImportaproveedorModule')) == true) {
            //si se pulsa submit de configuración enviamos los datos de configuración pasando por la función postProcessConfiguración y volvemos a configure.tpl
            $this->postProcessConfiguracion();

            //$this->context->smarty->assign('module_dir', $this->_path);

            $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        
        }
        elseif (((bool)Tools::isSubmit('submitCatalogos')) == true) {
            //si se pulsa Mostrar para el csv, se envía a postPorcessMuestraCatalogo, que devuelve $output con los datos del csv en una tabla
            $output = $this->postProcessMuestraCatalogo();  
            //mostramos $output sin renderForm
            return $output;          
        }
        elseif (((bool)Tools::isSubmit('submitSubir')) == true) {
            //si se pulsa Subir para el csv, se envía a postProcessSubirCatalogo, que subirá el archivo al servidor web, a la carpeta sincronizada
            $output = $this->postProcessSubirCatalogo();  
            //mostramos $output sin renderForm
            return $output;          
        }
        elseif (((bool)Tools::isSubmit('submitInsertar')) == true) {
            //si se pulsa insertar catálogo, se envía a postProcessInsertarCatalogo, que nos mostrará el contenido de la carpeta donde se suben los archivos y nos permitirá escoger uno para insertar los datos en la tabla frik_aux_import_catalogos que luego deberemos actualizar hacia frik_import_catalogos
            $output = $this->postProcessInsertarCatalogo();  
            //mostramos $output sin renderForm
            return $output;          
        }
        elseif (((bool)Tools::isSubmit('submitArchivoInsertar')) == true) {
            //si se pulsa insertar datos tras escoger un catálogo, se envía a postProcessInsertarDatos, que insertará los datos en la tabla frik_aux_import_catalogos que luego deberemos actualizar hacia frik_import_catalogos
            $output = $this->postProcessInsertarDatos();  
            //mostramos $output sin renderForm
            return $output;          
        }
        elseif (((bool)Tools::isSubmit('submitAsignarPermitirPedido')) == true) {
            //si se pulsa asignar permitir pedido, se envía a postProcessAsignarPermitirPedido, que analizará los productos que pueden ponerse en permitir pedido en función de la tabla frik_import_catalogos
            $output = $this->postProcessAsignarPermitirPedido();  
            //mostramos $output sin renderForm
            return $output;          
        }
        elseif (((bool)Tools::isSubmit('submitProveedores')) == true) {
            //si se pulsa asignar proveedores, se envía a postProcessAsignarProveedores, que mostrará los proveedores que tenemos "asignados" al módulo, pudiendo añadir otros, y esa lista se guardará en lafrips_configuration. Los proveedores asignados será sobre los que permitiremos el proceso automático de permitir pedido en función de la tabla frik_import_catalogos. Podrá haber proveedores que solo estén en la tabla para facilitar la creación de producto.
            $output = $this->postProcessAsignarProveedores();  
            //mostramos $output sin renderForm
            return $output;          
        }
        elseif (((bool)Tools::isSubmit('submit_proveedores_automatizados')) == true) {
            //si se pulsa proveedores automatizados, se envía a postProcessProveedoresAutomatizados, donde actualizaremos los proveedores configurados como automatizados en la tabla lafrips_configuration
            $output = $this->postProcessProveedoresAutomatizados();  
            //mostramos $output sin renderForm
            return $output;          
        }
        else {
            //si se carga el módulo sin haberse pulsado ningún submit
            //$this->context->smarty->assign('module_dir', $this->_path);
            $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
            
        }

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;   
        //no ponemos submit_action ya que al poner dos forms con el helper, les ponemos manualmente un nombre a cada submit, y en getContent dirigiremos la acción según el submit pulsado     
        //$helper->submit_action = 'submitImportaproveedorModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        //return $helper->generateForm(array($this->getConfigForm()));
        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        //pongo dos formularios diferentes en la página de configuración, uno para la configuración real y otro que me servirá para importar los datos de los catálogos desde un csv,etc
        //de momento quiero que solo el empleado con id 22 (yo) pueda ver el formulario de subida de catálogos, etc, y que la configuración general esté visible para todos, de modo que genero aquí los dos formularios y en el return meto lo que corresponda según el id_employee del context
        //formulario para gestión de catálogos
        $form_catalogos = array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('CATÁLOGOS'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(                               
                    array(
                        'col' => 6,
                        'type' => 'file',
                        'prefix' => '<i class="icon icon-folder-open"></i>',                        
                        'name' => 'csv_importado',
                        'id' => 'csv_importado',
                        'label' => $this->l('Selecciona archivo CSV (máx 2Mb)'),
                        'desc' => $this->l('Selecciona para visualizar un Catálogo con formato CSV UTF-8 Separado por comas. El nombre del archivo debe contener las siglas establecidas para el proveedor correspondiente (HEO, ABY, KAR, RED, etc.). EL TAMAÑO MÁXIMO DEL ARCHIVO SON 2Mb (bye bye HEO)'),
                        'size' => 20,
                    ),                                       
                ),
                'buttons' => array(
                    array(
                        'title' => 'ASIGNAR PERMITIR PEDIDO',   
                        'name' => 'submitAsignarPermitirPedido', 
                        'icon' => 'process-icon-thumbs-up icon-thumbs-up', 
                        'id' => 'asignar_permitir_pedido', 
                        'type' => 'submit',                                         
                    ),
                    array(
                        'title' => 'GESTIÓN PROVEEDORES', //asignaremos a cuales de los proveedores en tabla permitimos asignación automática permitir pedido
                        'name' => 'submitProveedores', 
                        'icon' => 'process-icon-truck icon-truck', 
                        'id' => 'asignar_proveedores', 
                        'type' => 'submit',                                        
                    ),
                    array(
                        'title' => 'INSERTAR CATÁLOGO EN TABLA',   
                        'name' => 'submitInsertar', 
                        'icon' => 'process-icon-table icon-table', 
                        'id' => 'insertar_catalogo', 
                        'type' => 'submit',                                        
                    ),
                    array(
                        'title' => 'Subir a servidor',   
                        'name' => 'submitSubir', 
                        'icon' => 'process-icon-upload icon-upload', 
                        'id' => 'subir_servidor', 
                        'class' => 'btn btn-default pull-right',
                        'type' => 'submit',                                       
                    ),
                ),
                // 'submit' => array(
                //     'title' => $this->l('Subir a servidor'),
                //     //ponemos nombre a este submit, ya que al tener dos formularios con el mismo helper, no asignamos el nombre desde el helper
                //     'name' => 'submitSubir',
                // ),
                'submit' => array(
                    'title' => $this->l('Mostrar'),
                    //ponemos nombre a este submit, ya que al tener dos formularios con el mismo helper, no asignamos el nombre desde el helper
                    'name' => 'submitCatalogos',
                    'icon' => 'process-icon-preview icon-preview', 
                ),
            ),
        );

        //formulario de configuración
        $form_configuracion = array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('CONFIGURACIÓN'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',                        
                        'label' => $this->l('Automatizar Permitir Pedidos').' <i class="icon icon-thumbs-up"></i>',
                        'name' => 'IMPORTA_PROVEEDOR_PERMITIR_PEDIDO',
                        'is_bool' => true,
                        'desc' => $this->l('Aplicar Permitir Pedido en los productos que se agoten en función de la disponibilidad en catálogo de proveedor'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),                    
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-euro"></i>',                        
                        'name' => 'IMPORTA_PROVEEDOR_PRECIO_LIMITE',
                        'label' => $this->l('PVP'),
                        'desc' => $this->l('Introduce el precio de venta límite por debajo del cual no se activará Permitir Pedido al producto agotado en función de su antigüedad. Introduce 0 para no poner límite.'),
                        'size' => 20,
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-calendar"></i>',
                        'desc' => $this->l('Introduce la antigüedad máxima, en días enteros, a partir de la cual se aplicará el precio límite. Introduce 0 para no poner límite.'),
                        'name' => 'IMPORTA_PROVEEDOR_ANTIGUEDAD',
                        'label' => $this->l('Antigüedad'),
                        'size' => 20,
                    ),                    
                ),
                'buttons' => array(
                    array(
                        'title' => 'RESETEAR PERMITIR PEDIDO',   
                        'name' => 'RESETEAR_PERMITIR_PEDIDO', 
                        'icon' => 'process-icon-thumbs-down icon-thumbs-down', 
                        'id' => 'RESETEAR_PERMITIR_PEDIDO',                                         
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    //ponemos nombre a este submit, ya que al tener dos formularios con el mismo helper, no asignamos el nombre desde el helper
                    'name' => 'submitImportaproveedorModule',
                ),
            ),
        );

        //mostramos todo si id == 22
        if( Context::getContext()->employee->id == 22){
            return array(
                $form_catalogos,
                $form_configuracion,
            );
        } else {
            return array(
                $form_configuracion,
            );
        }
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'IMPORTA_PROVEEDOR_PERMITIR_PEDIDO' => Configuration::get('IMPORTA_PROVEEDOR_PERMITIR_PEDIDO'),
            'IMPORTA_PROVEEDOR_PRECIO_LIMITE' => Configuration::get('IMPORTA_PROVEEDOR_PRECIO_LIMITE'),
            'IMPORTA_PROVEEDOR_ANTIGUEDAD' => Configuration::get('IMPORTA_PROVEEDOR_ANTIGUEDAD'),
        );
    }

    /**
     * Save form data.
     * Creamos un postProcess para cada submit, uno guardará la configuración del módulo y el otro se utilizará para importar catálogos desde csv
     */
    protected function postProcessConfiguracion()
    {
        //recogemos los valores del formulario de configuración
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            //guardamos en BD ,tabla configuration la configuración del módulo
            if ($key == 'IMPORTA_PROVEEDOR_PERMITIR_PEDIDO'){
                Configuration::updateValue($key, Tools::getValue($key));

            } elseif ($key == 'IMPORTA_PROVEEDOR_PRECIO_LIMITE'){
                //aseguramos que el precio tenga formato con separador decimal punto
                $precio_limite = str_replace(',','.', Tools::getValue($key));
                //comprobamos que es un número
                if (is_numeric($precio_limite)){                    
                    Configuration::updateValue($key, $precio_limite);
                }

            } elseif ($key == 'IMPORTA_PROVEEDOR_ANTIGUEDAD'){
                $antiguedad = round(Tools::getValue($key) ,0);
                //comprobamos que es un número
                if (is_numeric($antiguedad)){                    
                    Configuration::updateValue($key, $antiguedad);
                }
            }
            
        }
    }

    protected function postProcessMuestraCatalogo()
    {
        //mostrar error si el tamaño del archivo es superior a 2 Mb
        // if ($_FILES['csv_importado']['size'] > 2000000) {
        //     echo "Sorry, your file is too large.";
            
        // } 
        if(isset($_FILES['csv_importado']) && $_FILES['csv_importado']['size'] > 0) {
            //recibimos y mostramos el archivo subido en el formulario Catálogos
            $nombre_archivo = $_FILES['csv_importado']['name'];
            $explode_nombre_archivo = explode(".",$nombre_archivo);
            //$explode_nombre_archivo es un array con las dos partes de dividir por el punto. current es el primer elemento del array y end el último

            if(strtolower(end($explode_nombre_archivo)) == "csv"){        
                //Correcto, el archivo tiene extensión csv, averiguamos si contiene el nombre del proveedor
                //29/11/2019 HEO, RED, KAR, ABY
                if (strpos(strtolower($explode_nombre_archivo[0]),'aby') !== false){ 
                    $proveedor = 'Abysse';
                } elseif (strpos(strtolower($explode_nombre_archivo[0]),'red') !== false){ 
                    $proveedor = 'Redstring';
                } elseif (strpos(strtolower($explode_nombre_archivo[0]),'heo') !== false){ 
                    $proveedor = 'Heo';
                } elseif (strpos(strtolower($explode_nombre_archivo[0]),'kar') !== false){ 
                    $proveedor = 'Karactermanía';
                } else {
                    $proveedor = 'Proveedor No indicado - Comprobar';
                }

                $nombre_archivo_tmp = $_FILES['csv_importado']['tmp_name'];
                $handle = fopen($nombre_archivo_tmp, "r");
                
                $tabla = '<table class="table"><caption>Contenido de <h1>'.$nombre_archivo.'</h1><br><h2>Catálogo '.$proveedor.'</h2></caption><tbody>';

                //si el catálogo es Karactermanía no lleva cabeceras, se las ponemos
                if (($proveedor == 'Karactermanía')){                        
                    $tabla .= '<th>Referencia del artículo</th><th>Descripción (título)</th><th>EAN</th><th>Precio</th><th>Foto</th><th>Familia</th><th>Clase</th><th>Formato</th><th>En Blanco</th><th>Detalle artículo 1</th><th>Detalle artículo 2</th><th>Detalle artículo 3</th><th>Material del bolso</th><th>PVPR ES</th><th>PVPR FR</th><th>PVPR IT</th><th>PVPR DE</th><th>PVPR UK</th><th>Outlet (S/N)</th><th>Stock</th>';
                }

                $sombreado = 0;
                //el tamaño máximo por defecto para un archivo en formulario type file esta configurado en el servidor como 2Mb (phpini), así que archivos como el de HEO no los leerá
                while(!feof($handle)) {                       
                    //fgets() va leyendo cada línea del archivo
                    //parece que feof() permite un loop extra, ponemos la condición de que fgets() encuentre algo para seguir.
                    if ($linea = fgets($handle)){                    
                        $campos = explode(';', $linea);

                        $num_campos = count($campos);
                        //$num_campos = 3;                    
                        
                        $tabla .= '<tr ';
                        if ($sombreado % 2 !== 0) {
                            $tabla .= ' bgcolor="#EEEEEE">';
                        }else{
                            $tabla .= '>';    
                        }
                        
                        $contador = 0;                
                        while ($contador < $num_campos){
                            if ($sombreado == 0){   
                                if ($proveedor == 'Karactermanía'){
                                    $contenido = trim($campos[$contador]);
                                    $tabla .= '<td>'.$contenido.'</td>';
                                } else {
                                    $contenido = trim($campos[$contador]);
                                    $tabla .= '<th>'.$contenido.'</th>';
                                }                            
                            } else {
                                $contenido = trim($campos[$contador]);
                                $tabla .= '<td>'.$contenido.'</td>';
                            }
                            $contador++;
                        }
                        $tabla .= '</tr>';
                        $sombreado++;
                
                    }
                    
                }
                fclose($nombre_archivo_tmp);            
                $tabla .= '</tbody></table>';
            } else {
                $tabla .= '<h1>El archivo no es csv</h1>';
            }
        } else {
            $tabla = '<h1>Archivo no seleccionado.</h1>';
        }


        $output = '<div class="panel">'.$tabla.'</div>';

        return $output;
    }

    //Subir archivo de catálogo al servidor
    //Para producción cambiar target_dir a la carpeta /tmp  que es la sincronizada con el servidor de base de datos, para poder hacer el insert después
    protected function postProcessSubirCatalogo()
    {
        $info = '';
        if(isset($_FILES['csv_importado']) && $_FILES['csv_importado']['size'] > 0) {
            //$target_dir = '/var/www/vhost/lafrikileria.com/home/html/tmpAuxiliar/';
            //usamos la constante PS_ROOT_DIR que nos lleva a la raíz de la web (en test si se ejecuta el módulo en test)
            $target_dir = _PS_ROOT_DIR_.'/tmpAuxiliar/';
            
            $target_file = $target_dir.basename($_FILES['csv_importado']['name']);
            $uploadOk = 1;
            $fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
            // Check if image file is a actual image or fake image
            // if(isset($_POST["submitSubir"])) {
            //     $check = getimagesize($_FILES['csv_importado']["tmp_name"]);
            //     if($check !== false) {
            //         $info .= "File is an image - " . $check["mime"] . ".";
            //         $uploadOk = 1;
            //     } else {
            //         $info .= "File is not an image.";
            //         $uploadOk = 0;
            //     }
            // }

            // Comprobamos si el archivo ya existe
            if (file_exists($target_file)) {
                $info .= "<br>Atención, el archivo ya existe.";
                $uploadOk = 0;
            }
            // Comprobamos el tamaño, en este caso es el máximo permitido en php.ini por lo que da igual, ya que si lo supera, no habrá podido intentar subirlo
            if ($_FILES['csv_importado']["size"] > 2097152) {
                $info .= "<br>Atención, archivo demasiado grande.";
                $uploadOk = 0;
            }
            // Comprobamos que sea un csv
            if($fileType != "csv"){
                $info .= "<br>Atención, el archivo debe ser csv.";
                $uploadOk = 0;
            }
            // Si ha habido error muestra mensaje
            if ($uploadOk == 0) {
                $info .= "<br>Lo siento, tu archivo no se ha subido.";
            // Si está todo bien intenta la subida
            } else {
                if (move_uploaded_file($_FILES['csv_importado']['tmp_name'], $target_file)) {
                    $info .= "<h2>El archivo ". basename( $_FILES['csv_importado']['name']). " ha subido correctamente.</h2> <br><br><h2><strong>Recuerda que debes esperar al menos dos minutos para que el contenido de la carpeta se sincronice con el servidor de base de datos antes de poder insertar los datos en la tabla de catálogos de proveedor.</strong></h2>";
                } else {
                    $info .= "<br>Lo siento, ha habido un error subiendo el archivo.";
                }
            }

        } else {
            $info = '<h1>Archivo no seleccionado.</h1>';
        }   

        $output = '<div class="panel">'.$info.'</div>';

        return $output;
    }

    //Mostrar archivos dentro de carpeta de subidas, una vez seleccionado uno se envía el dato a la función postProcessInsertarDatos() para insertar datos si corresponde.
    //20/08/2020 vamos a reutilizar todo esto para hacer la subida de catálogos, pero mediante php, y no haciendo insert desde el csv leyendo mediante la carpeta sincronizada entre servidor web y servidor base de datos. De este modo da igual el tamaño del archivo, lo dejamos manualmente en la carpeta /proveedores/subida del servidor, lo mostramos en esta función, y el escogido se procesa con funciones de php para insertar los datos en frik_aux_import_catalogos, que después se actualizará con frik_import_catalogs    
    protected function postProcessInsertarCatalogo() 
    {
        //Mostraremos el contenido de la carpeta  de destino de subida de archivos (proveedores/subidas)
        $contenido = '';
        // $directorio = _PS_ROOT_DIR_.'/../proveedores/subidas/'; TEST
        $directorio = _PS_ROOT_DIR_.'/proveedores/subidas/';
        $archivos = scandir($directorio, 1);
        $contenido .= '<form method="post" action="">
        <h2>Contenido carpeta /proveedores/subidas del servidor web</h2><br>
        <h3>Selecciona el archivo a procesar</h3>';
        $contador = 0;
        foreach ($archivos AS $archivo){
            $fileType = strtolower(pathinfo($archivo,PATHINFO_EXTENSION));
            //si el archivo no es csv hacemos el radiobutton disabled
            // if($fileType != "csv"){
            //     $contenido .= '<input type="radio" id="radio_insertar'.$contador.'" name="archivo_insertar" value="'.$archivo.'" class="radio-custom" disabled>
            //     <label for="radio_insertar'.$contador.'"  class="radio-custom-label"> '.$archivo.'</label><br>';
            // } else {
            //     $contenido .= '<input type="radio" id="radio_insertar'.$contador.'" name="archivo_insertar" value="'.$archivo.'" class="radio-custom">
            //     <label for="radio_insertar'.$contador.'"  class="radio-custom-label"> '.$archivo.'</label><br>';
            // }
            $contenido .= '<input type="radio" id="radio_insertar'.$contador.'" name="archivo_insertar" value="'.$archivo.'" class="radio-custom">
                <label for="radio_insertar'.$contador.'"  class="radio-custom-label"> '.$archivo.'</label><br>';
            $contador++;
        }
        $contenido .= '<br><input type="submit" name="submitArchivoInsertar" value="Insertar Datos" class="btn btn-default icon-table">
        </form>';

        $output = '<div class="panel">'.$contenido.'</div>';
        //Al hacer submit en este formulario volvemos a pasar por getContent() que nos enviará a la función que haga el insert en la tabla
        return $output;
    }
    /*
        Función que inserta los datos del archivo seleccionado en la tabla frik_aux_import_catalogos, en función del proveedor.
        Como medida de seguridad tan solo comprobamos el número de columnas de cada catálogo, no podemos comporbar el ean porque salvo para Cerdá, todos los demás pueden tener ean o no,o no ean en muchas casillas así que hay que tragar con lo que haya.
        También vamos a usarlo para los productos prepedidos. En el if que reconoce si es RED, ABY o HEO, cuando compruebe por regex que no tiene formato catálogo HEO20080213 comprobará si tiene prepedidos_HEO_24_12_20 y si lo tiene subirá los datos a la tabla frik_productos_prepedidos, la cual debe ser vaciada antes del proceso par que funcione bien. Este proceso requiere buscar el producto en prestashop para saber si existe y poder introducir el id_product
    */
    protected function postProcessInsertarDatos() 
    {
        //obtenemos el radio button seleccionado en el formulario para escoger archivo a insertar
        $archivo_escogido = Tools::getValue('archivo_insertar');
        $contenido = '';
        $path = '/var/www/vhost/lafrikileria.com/home/html/proveedores/subidas/';
        $mensaje = '';
        //descomponemos el nombre para ver si tiene el formato de subida de catálogo
        $explode_nombre_archivo = explode(".",$archivo_escogido);
        $es_catalogo = 0; 
        if (strpos(strtolower($explode_nombre_archivo[0]),'red') !== false){
            $proveedor = 'Redstring';
            $pattern = '/^RED_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            //creamos pattern para los productos prepedidos
            $pattern2 = '/^prepedidos_RED_[0-9]{2}_[0-9]{2}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Redstring, si no hay 10 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 10)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 9 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[1]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $descripcion = pSQL(trim($campos[2]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }

                        $url_producto = trim($campos[3]);
                        if (!$url_producto || $url_producto == '' || is_null($url_producto)){
                            $url_producto = '';
                        }

                        $precio = str_replace(',','.',trim($campos[4])); //cambiamos , por .
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        $stock = trim($campos[5]);
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }

                        $atributo = trim($campos[6]);
                        if (!$atributo || $atributo == '' || is_null($atributo)){
                            $atributo = '';
                        }

                        // $dummy = trim($campos[7]);      campos 6 no tiene nada útil                       
                        $url_imagen = trim($campos[8]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        // 13/05/2021 - hacemos padding a la izquierda a los ean rellenando con 0 hasta 13 cifras los que no las tengan
                        $ean = trim($campos[9]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        } else if (strlen($ean) < 13) {
                            $ean = str_pad($ean,13,'0',STR_PAD_LEFT);
                        }

                        //para redstring:
                        $id_proveedor = 24;
                        $nombre_proveedor = 'Redstring';
                        
                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }

                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, url_producto, url_imagen, stock, estado, disponibilidad, atributo, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$atributo.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } elseif (preg_match($pattern2, $explode_nombre_archivo[0], $match)) { //probamos si encaja con productos prepedidos
                //son productos prepedidos al distribuidor
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para productos prepedidosRedstring, si no hay 7 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 7)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 7 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[1]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $cantidad = trim($campos[2]);
                        if (!$cantidad || $cantidad == '' || is_null($cantidad)){
                            $cantidad = 0;
                        }

                        $estado = pSQL(trim($campos[3]));
                        if (!$estado || $estado == '' || is_null($estado)){
                            $estado = 'ErrorCSV';
                        }

                        $fecha = trim($campos[4]);
                        if (!$fecha || $fecha == '' || is_null($fecha)){
                            $fecha = 'ErrorCSV';
                        }

                        $precio = str_replace(',','.',trim($campos[5])); //cambiamos , por .
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        //ahora para saber que productos están en Prestashop y marcar en la tabla
                        $sql_productos_prestashop = 'SELECT GROUP_CONCAT(id_product) AS productos FROM lafrips_product_supplier 
                        WHERE id_product_attribute = 0 
                        AND product_supplier_reference = "'.$referencia.'"';

                        $productos_prestashop = Db::getInstance()->ExecuteS($sql_productos_prestashop);

                        if ($productos_prestashop[0]['productos']) {
                            $existe_prestashop = 1;
                            $id_frikileria = $productos_prestashop[0]['productos'];
                        } else {
                            $existe_prestashop = 0;
                            $id_frikileria = '';
                        }                    

                        //introducimos la línea en frik_productos_prepedidos
                        $sql_insert_datos = 'INSERT INTO frik_productos_prepedidos
                        (nombre, referencia_producto, precio, estado_pedido, cantidad_pedido, fecha_llegada, disponible_frikileria, id_frikileria) VALUES 
                        ("'.$nombre.'", "'.$referencia.'", "'.$precio.'", "'.$estado.'", '.$cantidad.', "'.$fecha.'", '.$existe_prestashop.', "'.$id_frikileria.'")';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' - nombre '.$nombre;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos o productos prepedidos.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'aby') !== false){
            $proveedor = 'Abysse';
            $pattern = '/^ABY_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            //creamos pattern para los productos prepedidos
            $pattern2 = '/^prepedidos_ABY_[0-9]{2}_[0-9]{2}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Abysse, si no hay 21 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    //16/11/2020 - han añadido un campo de pais de procedencia al final (última columna) cambiamos a 22
                    //12/01/2021 parece que vuelven a 20 columnas
                    if ((count($campos) != 20)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 20 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[1]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $stock = trim($campos[5]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }

                        $estado = trim($campos[7]); 
                        if (!$estado || $estado == '' || is_null($estado)){
                            $estado = '';
                        }

                        $ean = trim($campos[8]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $atributo = trim($campos[9]);
                        if (!$atributo || $atributo == '' || is_null($atributo)){
                            $atributo = '';
                        }

                        $precio = str_replace(',','.',trim($campos[10])); //cambiamos , por .  
                        //a 26/04/2022 aplicamos 4% descuento
                        $precio = $precio - ($precio*0.04);                      
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }
                                                
                        //para Abysse:
                        $id_proveedor = 14;
                        $nombre_proveedor = 'Abysse';
                        $url_imagen = 'http://emailing.abyssecorp.com/'.$referencia.'.jpg';
                        $url_producto = 'http://trade.abyssecorp.com/e/en/recherche?controller=search&orderby=date_add&orderway=desc&search_query='.$referencia; 
                        $descripcion = $url_producto;           
                        
                        //si estado es pre-order no está disponible, si no  es In stock o low stock 
                        $pattern = '/O|order/';                        
                        if (preg_match($pattern, $estado)) {
                            $disponibilidad = 0;
                        } else {
                            $disponibilidad = 1;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, url_producto, url_imagen, stock, estado, disponibilidad, atributo, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$atributo.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } elseif (preg_match($pattern2, $explode_nombre_archivo[0], $match)) { //probamos si encaja con productos prepedidos
                //son productos prepedidos al distribuidor
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para productos prepedidos Abysse, si no hay 12 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 12)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 12 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[1]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[2]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $fecha_pedido = trim($campos[3]);
                        // if (!$fecha_pedido || $fecha_pedido == '' || is_null($fecha)){
                        if (!$fecha_pedido || $fecha_pedido == ''){
                            $fecha_pedido = 'ErrorCSV';
                        }

                        $cantidad = trim($campos[7]);
                        if (!$cantidad || $cantidad == '' || is_null($cantidad)){
                            $cantidad = 0;
                        }

                        $precio = trim($campos[8]); //cambiamos , por .
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        $estado = pSQL(trim($campos[10]));
                        if (!$estado || $estado == '' || is_null($estado)){
                            $estado = 'ErrorCSV';
                        }

                        $fecha_llegada = trim($campos[11]);
                        // if (!$fecha_llegada || $fecha_llegada == '' || is_null($fecha)){
                        if (!$fecha_llegada || $fecha_llegada == ''){
                            $fecha_llegada = 'ErrorCSV';
                        }       

                        //ahora para saber que productos están en Prestashop y marcar en la tabla
                        $sql_productos_prestashop = 'SELECT GROUP_CONCAT(id_product) AS productos FROM lafrips_product_supplier 
                        WHERE id_product_attribute = 0 
                        AND product_supplier_reference = "'.$referencia.'"';

                        $productos_prestashop = Db::getInstance()->ExecuteS($sql_productos_prestashop);

                        if ($productos_prestashop[0]['productos']) {
                            $existe_prestashop = 1;
                            $id_frikileria = $productos_prestashop[0]['productos'];
                        } else {
                            $existe_prestashop = 0;
                            $id_frikileria = '';
                        }                    

                        //introducimos la línea en frik_productos_prepedidos
                        $sql_insert_datos = 'INSERT INTO frik_productos_prepedidos
                        (nombre, referencia_producto, precio, fecha_pedido, estado_pedido, cantidad_pedido, fecha_llegada, disponible_frikileria, id_frikileria) VALUES 
                        ("'.$nombre.'", "'.$referencia.'", "'.$precio.'", "'.$fecha_pedido.'", "'.$estado.'", '.$cantidad.', "'.$fecha_llegada.'", '.$existe_prestashop.', "'.$id_frikileria.'")';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' - nombre '.$nombre;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos o productos prepedidos.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 
        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'kar') !== false){
            $proveedor = 'Karactermanía';
            $pattern = '/^KAR_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera y segunda línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para el nuevo de Karactermanía (03/08/2021), si no hay 59 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 59)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 59 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    //la segunda línea son los nombres de las columnas, tampoco se procesa pero comprobamos algunas para asegurar que están ordenadas
                    $campos = fgetcsv($handle, 0, ";");

                    if ((trim($campos[0]) !== 'SKU') || (trim($campos[3]) !== 'Product ID') || (trim($campos[10]) !== 'Model Number') || (trim($campos[14]) !== 'Cost Price') || (trim($campos[23]) !== 'Stock') || (trim($campos[47]) !== 'Package Weight') || (trim($campos[54]) !== 'URL Image 1')) {
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Las cabeceras de las columnas no coinciden con los campos configurados en importaproveedor.php'; 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    $productos = 0;
                    $no_procesados = 0;                        
                    
                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        } else {
                            //las referencias tienen que tener 5 cifras, las que son números menores hay que rellenarlas a la izquierda hasta llegar a 5
                            $referencia = str_pad($referencia , 5, '0',STR_PAD_LEFT);
                        }

                        $nombre = pSQL(trim($campos[1]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $ean = trim($campos[3]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $precio = str_replace(',','.',trim($campos[14])); //cambiamos , por .
                        //a 26/04/2022 aplicamos 30% descuento
                        $precio = $precio - ($precio*0.3);
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        $peso = str_replace(',','.',trim($campos[45])); //cambiamos , por .
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }

                        //el nuevo catálogo trae varios campos para la descripción. Voy a unirlos todos, con el de medidas y que quién cree el producto lo deje bien
                        $descripcion = pSQL(trim($campos[26])).'<br><br>'.pSQL(trim($campos[17])).'<br><br>'.pSQL(trim($campos[18])).'<br><br>'.pSQL(trim($campos[19])).'<br><br>'.pSQL(trim($campos[20]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }
                        //a descripcion le añadimos el link al producto en la web de karactermanía
                        $descripcion = $descripcion.'<br><br>https://karactermania.com/es/catalogsearch/result?query='.$referencia;

                        $url_imagen = trim($campos[54]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        $url_imagen_2 = trim($campos[55]);
                        if (!$url_imagen_2 || $url_imagen_2 == '' || is_null($url_imagen_2)){
                            $url_imagen_2 = '';
                        }

                        $url_imagen_3 = trim($campos[56]);
                        if (!$url_imagen_3 || $url_imagen_3 == '' || is_null($url_imagen_3)){
                            $url_imagen_3 = '';
                        }

                        $url_imagen_4 = trim($campos[57]);
                        if (!$url_imagen_4 || $url_imagen_4 == '' || is_null($url_imagen_4)){
                            $url_imagen_4 = '';
                        }

                        $url_imagen_5 = trim($campos[58]);
                        if (!$url_imagen_5 || $url_imagen_5 == '' || is_null($url_imagen_5)){
                            $url_imagen_5 = '';
                        }                        

                        $url_producto = 'https://karactermania.com/es/catalogsearch/result?query='.$referencia;
                        
                        $stock = trim($campos[23]);
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }

                        //para karactermanía:
                        $id_proveedor = 53;
                        $nombre_proveedor = 'Karactermania';
                        
                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }
                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5, stock, estado, disponibilidad, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', '.$peso.', "'.$url_producto.'", "'.$url_imagen.'", "'.$url_imagen_2.'", "'.$url_imagen_3.'", "'.$url_imagen_4.'", "'.$url_imagen_5.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.'  url_imagen '.$url_imagen;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            }     
        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'heo') !== false){
            $proveedor = 'Heo';
            $pattern = '/^HEO_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            //creamos pattern para los productos prepedidos
            $pattern2 = '/^prepedidos_HEO_[0-9]{2}_[0-9]{2}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para HEO, si no hay 25 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    //07/10/2021 Han añadido una columna al final RCF, recargo por costes de flete.
                    if ((count($campos) != 25)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 25 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $nombre = pSQL(trim($campos[0]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $descripcion = pSQL(trim($campos[1]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }

                        $precio = str_replace(',','.',trim($campos[2])); //cambiamos , por .
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        //20/08/2021 Por sobrecostes de flete han añadido 3.5% al coste de productos anteriores al 12 julio 2021. Temporalmente, añadimos ese sobrecoste al coste, comparando la fechadeprimeradisponibilidad del catálogo. Si vuelve a cambiar basta con comentar este código:
                        $primera_fecha_disponibilidad = trim($campos[8]);                        
                        if (!$primera_fecha_disponibilidad || $primera_fecha_disponibilidad == '' || is_null($primera_fecha_disponibilidad)){
                            //si no hay fecha (error) ponemos el 3.5% por defecto
                            $precio = $precio*1.035;
                        } else {
                            //la fecha llega en formato 12.01.2020, hay que pasarla a 2020-01-12. No hace  falta pasarlas a Date solo para saber si es mayor una que otra.
                            //hacemos explode por el . , después invertimos el orden del array resultante y después hacemos implode con - , quedando la fecha en formato 2020-01-12
                            $primera_fecha_disponibilidad = explode(".", $primera_fecha_disponibilidad);
                            $primera_fecha_disponibilidad = array_reverse($primera_fecha_disponibilidad);
                            $primera_fecha_disponibilidad = implode("-", $primera_fecha_disponibilidad);
                            //si 2021-07-12 es menor que la fecha de disponibilidad es que hay que aplicar el 3.5%
                            if ($primera_fecha_disponibilidad < "2021-07-12") {
                                $precio = $precio*1.035;
                            }

                        }
                        //comentar hasta aquí si se anula lo del sobrecoste de flete

                        $url_imagen = trim($campos[3]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        $ean = trim($campos[4]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $referencia = trim($campos[5]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $peso = str_replace(',','.',trim($campos[6])/1000); //cambiamos , por . y dividimos porque son gramos
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }
                        
                        $estado = trim($campos[7]); 
                        if (!$estado || $estado == '' || is_null($estado)){
                            $estado = '';
                        }

                        $disponibilidad_heo = trim($campos[12]); 
                        if (!$disponibilidad_heo || $disponibilidad_heo == '' || is_null($disponibilidad_heo)){
                            $disponibilidad_heo = 0;
                        }
                        if ($disponibilidad_heo == 2) {
                            $disponibilidad = 0; 
                        } elseif (($disponibilidad_heo == 0) || ($disponibilidad_heo == 1)) {
                            $disponibilidad = 1; 
                        } elseif (($disponibilidad_heo == 3) || ($disponibilidad_heo == 4)) {
                            $disponibilidad = 2; 
                        } else {
                            $disponibilidad = 0; 
                        }

                        
                        $fecha_llegada = trim($campos[23]);                        
                        if (!$fecha_llegada || $fecha_llegada == '' || is_null($fecha_llegada)){
                            $fecha_llegada = '0000-00-00';
                        } else {
                            //la fecha llega en formato 12.01.2020, hay que pasarl a 2020-01-12. Cambiamos primero . por -
                            $fecha_llegada = date("Y-m-d", strtotime(str_replace('.','-',$fecha_llegada)));
                        }

                        
                        
                        $url_producto = 'https://www.heo.com/de/es/product/'.$referencia;
                        
                        $descripcion = $descripcion.'<br><br>Peso= '.$peso.' <br><br>https://www.heo.com/de/es/product/'.$referencia;

                        //para HEO:
                        $id_proveedor = 4;
                        $nombre_proveedor = 'Heo'; 

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, url_imagen, estado, disponibilidad, fecha_llegada, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', '.$peso.', "'.$url_producto.'", "'.$url_imagen.'", "'.$estado.'", '.$disponibilidad.', "'.$fecha_llegada.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen.' fecha_disponible= '.$fecha_llegada;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;

            } elseif (preg_match($pattern2, $explode_nombre_archivo[0], $match)) { //probamos si encaja con productos prepedidos
                //son productos prepedidos al distribuidor HEO
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para productos prepedidos Heo, si no hay 15 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    //01/08/2022 han añadido al final columna TARIC, aumentamos a 16 columnas
                    if ((count($campos) != 16)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 16 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $fecha_pedido = trim($campos[0]);
                        // if (!$fecha_pedido || $fecha_pedido == '' || is_null($fecha)){
                        if (!$fecha_pedido || $fecha_pedido == ''){
                            $fecha_pedido = 'ErrorCSV';
                        }
                        
                        $estado = pSQL(trim($campos[1]));
                        if (!$estado || $estado == '' || is_null($estado)){
                            $estado = 'ErrorCSV';
                        }

                        $referencia = trim($campos[2]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[3]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $precio = trim($campos[4]);
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        $cantidad = trim($campos[5]);
                        if (!$cantidad || $cantidad == '' || is_null($cantidad)){
                            $cantidad = 0;
                        }
                     
                        $fecha_llegada = trim($campos[10]);
                        // if (!$fecha_llegada || $fecha_llegada == '' || is_null($fecha)){
                        if (!$fecha_llegada || $fecha_llegada == ''){
                            $fecha_llegada = 'ErrorCSV';
                        }       

                        //ahora para saber que productos están en Prestashop y marcar en la tabla
                        $sql_productos_prestashop = 'SELECT GROUP_CONCAT(id_product) AS productos FROM lafrips_product_supplier 
                        WHERE id_product_attribute = 0 
                        AND product_supplier_reference = "'.$referencia.'"';

                        $productos_prestashop = Db::getInstance()->ExecuteS($sql_productos_prestashop);

                        if ($productos_prestashop[0]['productos']) {
                            $existe_prestashop = 1;
                            $id_frikileria = $productos_prestashop[0]['productos'];
                        } else {
                            $existe_prestashop = 0;
                            $id_frikileria = '';
                        }                    

                        //introducimos la línea en frik_productos_prepedidos
                        $sql_insert_datos = 'INSERT INTO frik_productos_prepedidos
                        (nombre, referencia_producto, precio, fecha_pedido, estado_pedido, cantidad_pedido, fecha_llegada, disponible_frikileria, id_frikileria) VALUES 
                        ("'.$nombre.'", "'.$referencia.'", "'.$precio.'", "'.$fecha_pedido.'", "'.$estado.'", '.$cantidad.', "'.$fecha_llegada.'", '.$existe_prestashop.', "'.$id_frikileria.'")';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' - nombre '.$nombre;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos o productos prepedidos.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            }    
        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'dif') !== false){
            $proveedor = 'Difuzed';
            $pattern = '/^DIF_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Difuzed, si no hay 32 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    //30/06/2022 Han añadido columna Discount % on ListPrice, en campo 12, pasan a ser 33
                    if ((count($campos) != 33)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 33 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    //11/03/2021 Han cambiado el formato de archivo y llega en CSV UTF-16LE, al descargarlo le cambio extensión a txt y lo abro y cambio de UTF-16 a UTF-8, guardamos y esto ya lo lee.

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {                        
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $atributo = trim($campos[1]);
                        if (!$atributo || $atributo == '' || is_null($atributo)){
                            $atributo = '';
                        }

                        $referencia_base = trim($campos[2]);  
                        if (!$referencia_base || $referencia_base == '' || is_null($referencia_base)){
                            // $referencia_base = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[4]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $ean = trim($campos[5]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        // $precio = str_replace(',','.',trim($campos[10])); //cambiamos , por .
                        //11/03/2021 cambian el archivo a csv y el precio lleva '.' . Hasta arreglarlo, divido /100 porque al guardar el csv pierde la puntuación
                        $precio = trim($campos[10]);

                        //a 21/08/2020 Difuzed nos hace un 10% de descuento en todo, lo aplicamos ya
                        //a 26/04/2022 cambiado a 7%
                        $precio = $precio - ($precio*0.07);
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        //30/06/2022 pasa de 15 a 16
                        $composicion = pSQL(trim($campos[16]));
                        if (!$composicion || $composicion == '' || is_null($composicion)){
                            $composicion = '';
                        }

                        //30/06/2022 pasa de 16 a 17
                        $color = pSQL(trim($campos[17]));
                        if (!$color || $color == '' || is_null($color)){
                            $color = '';
                        }

                        //30/06/2022 pasa de 17 a 18
                        $genero = pSQL(trim($campos[18]));
                        if (!$genero || $genero == '' || is_null($genero)){
                            $genero = '';
                        }

                        //30/06/2022 pasa de 18 a 19
                        $age_group = pSQL(trim($campos[19]));
                        if (!$age_group || $age_group == '' || is_null($age_group)){
                            $age_group = '';
                        }

                        //30/06/2022 pasa de 19 a 20
                        $longitud = pSQL(trim($campos[20]));
                        if (!$longitud || $longitud == '' || is_null($longitud)){
                            $longitud = '';
                        }

                        //30/06/2022 pasa de 20 a 21
                        $ancho = pSQL(trim($campos[21]));
                        if (!$ancho || $ancho == '' || is_null($ancho)){
                            $ancho = '';
                        }

                        //30/06/2022 pasa de 21 a 22
                        $alto = pSQL(trim($campos[22]));
                        if (!$alto || $alto == '' || is_null($alto)){
                            $alto = '';
                        }

                        //30/06/2022 pasa de 22 a 23
                        $peso = str_replace(',','.',trim($campos[23])/1000); //cambiamos , por . y dividimos porque son gramos
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }

                        //30/06/2022 pasa de 23 a 24
                        $stock = number_format(trim($campos[24]),0,'.',''); //el stock a veces aparece con decimales y da error, con esto, si no lo deja bien como el stock real, al menos pone 1 si es un número positivo con lo que la disponibilidad será correcta, lo que hace es decir que tenga 0 decimales, separador de decimales '.' y separador miles '', es decir, sin separador                       
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }

                        //30/06/2022 pasa de 26 a 27
                        $fecha_llegada = trim($campos[27]);                        
                        if (!$fecha_llegada || $fecha_llegada == '' || is_null($fecha_llegada)){
                            $fecha_llegada = '0000-00-00';
                        } else {
                            //la fecha llega en formato 12/01/2020, hay que pasarl a 2020-01-12. Si hacemos strtotime() lo pasa a formato americano mm-dd-YY, de modo que cambiamos primero / por -
                            $fecha_llegada = date("Y-m-d", strtotime(str_replace('/','-',$fecha_llegada)));
                        }

                        //30/06/2022 pasa de 28 a 29
                        $url_imagen = trim($campos[29]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        //30/06/2022 pasa de 29 a 30
                        $url_imagen_2 = trim($campos[30]);
                        if (!$url_imagen_2 || $url_imagen_2 == '' || is_null($url_imagen_2)){
                            $url_imagen_2 = '';
                        }

                        //30/06/2022 pasa de 30 a 31
                        $url_imagen_3 = trim($campos[31]);
                        if (!$url_imagen_3 || $url_imagen_3 == '' || is_null($url_imagen_3)){
                            $url_imagen_3 = '';
                        }

                        //30/06/2022 pasa de 31 a 32
                        $url_imagen_4 = trim($campos[32]);
                        if (!$url_imagen_4 || $url_imagen_4 == '' || is_null($url_imagen_4)){
                            $url_imagen_4 = '';
                        }

                        $url_producto = 'https://shop.difuzed.com/catalogsearch/result/?q='.$referencia;
                        
                        $descripcion = $url_producto.'\n'.$composicion.' - '.$color.'\n'.$longitud.'x'.$ancho.'x'.$alto.' - '.$peso.'gr\nGénero '.$genero.'\nGrupo edad '.$age_group;
                        
                        //para difuzed:
                        $id_proveedor = 7;
                        $nombre_proveedor = 'Difuzed';
                        
                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }
                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, referencia_proveedor_base, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, stock, estado, disponibilidad, fecha_llegada, atributo, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", "'.$referencia_base.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', '.$peso.', "'.$url_producto.'", "'.$url_imagen.'", "'.$url_imagen_2.'", "'.$url_imagen_3.'", "'.$url_imagen_4.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$fecha_llegada.'", "'.$atributo.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen.' fecha_llegada= '.$fecha_llegada;;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'cer') !== false){
            //con Cerdá, desde 26/08/2020 manejamos la disponibilidad del stock de los productos Cerdá Kids con un proceso que lee directamente el catálogo de la carpeta del servidor y pone o quita out_of_stock según el producto o combinación aparezca en dicho catálogo. De modo que a la tabla frik_import_catálogos ya solo subimos los que no son Kids, es decir, los que tienen cod_target = 5, los demás los ignoramos
            $proveedor = 'Cerdá';
            $pattern = '/^CER_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Cerdá, si no hay 45 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 45)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 45 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;     
                    
                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {                        
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        //primero comprobamos cod_target, si no es lifestyle adult pasamos al siguiente
                        $cod_target = trim($campos[43]);
                        if ($cod_target != 5){
                            continue;
                        }                        

                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        } 

                        $referencia_base = trim($campos[1]);  
                        if (!$referencia_base || $referencia_base == '' || is_null($referencia_base)){
                            $referencia_base = '';
                        }

                        $nombre = pSQL(trim($campos[2]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre_ing = pSQL(trim($campos[3]));
                        if (!$nombre_ing || $nombre_ing == '' || is_null($nombre_ing)){
                            $nombre_ing = 'ErrorCSV';
                        }

                        $cod_familia = trim($campos[4]);
                        if (!$cod_familia || $cod_familia == '' || is_null($cod_familia)){
                            $cod_familia = 0;
                        }                        

                        $cod_subfamilia = trim($campos[7]);
                        if (!$cod_subfamilia || $cod_subfamilia == '' || is_null($cod_subfamilia)){
                            $cod_subfamilia = 0;
                        }

                        $composicion = pSQL(trim($campos[12]));
                        if (!$composicion || $composicion == '' || is_null($composicion)){
                            $composicion = '';
                        }

                        $personaje = pSQL(trim($campos[14]));
                        if (!$personaje || $personaje == '' || is_null($personaje)){
                            $personaje = '';
                        }

                        $ean = trim($campos[21]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $medida_gen = pSQL(trim($campos[22]));
                        if (!$medida_gen || $medida_gen == '' || is_null($medida_gen)){
                            $medida_gen = '';
                        }

                        $peso = trim($campos[25]); 
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }

                        $url_imagen = trim($campos[26]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        $url_imagen_2 = trim($campos[27]);
                        if (!$url_imagen_2 || $url_imagen_2 == '' || is_null($url_imagen_2)){
                            $url_imagen_2 = '';
                        }

                        $url_imagen_3 = trim($campos[28]);
                        if (!$url_imagen_3 || $url_imagen_3 == '' || is_null($url_imagen_3)){
                            $url_imagen_3 = '';
                        }

                        $url_imagen_4 = trim($campos[29]);
                        if (!$url_imagen_4 || $url_imagen_4 == '' || is_null($url_imagen_4)){
                            $url_imagen_4 = '';
                        }

                        $url_imagen_5 = trim($campos[30]);
                        if (!$url_imagen_5 || $url_imagen_5 == '' || is_null($url_imagen_5)){
                            $url_imagen_5 = '';
                        }

                        $url_imagen_6 = trim($campos[31]);
                        if (!$url_imagen_6 || $url_imagen_6 == '' || is_null($url_imagen_6)){
                            $url_imagen_6 = '';
                        }

                        $desc_color = trim($campos[32]);
                        if (!$desc_color || $desc_color == '' || is_null($desc_color)){
                            $desc_color = '';
                        }

                        $desc_talla = trim($campos[33]);
                        if (!$desc_talla || $desc_talla == '' || is_null($desc_talla)){
                            $desc_talla = '';
                        }

                        $stock = trim($campos[37]);
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }

                        $pvp = trim($campos[38]); 
                        if (!$pvp || $pvp == '' || is_null($pvp)){
                            $pvp = 0;
                        }                        

                        $precio = trim($campos[40]); 
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }  

                        $descripcion = pSQL(trim($campos[41]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }                       

                        //a descripcion le añadimos el link al producto en la web de karactermanía
                        $descripcion = $descripcion.' <br><br>'.$composicion.' <br><br>'.$personaje.' <br><br>'.$desc_color.' <br><br>'.$desc_talla.' <br><br>'.$medida_gen;

                        $descripcion_ing = $nombre_ing.' <br><br>'.$composicion.' <br><br>'.$personaje.' <br><br>'.$desc_color.' <br><br>'.$desc_talla.' <br><br>'.$medida_gen;

                        if ($referencia_base) {
                            $url_producto = 'https://www.cerdagroup.com/es/product/show/'.$referencia_base.'/'.str_replace(' ','-', $nombre).'/';
                        } else {
                            $url_producto = 'https://www.cerdagroup.com/es/product/show/'.$referencia.'/'.str_replace(' ','-', $nombre).'/';
                        }

                        //para Cerdá:
                        $id_proveedor = 65;
                        $nombre_proveedor = 'Cerdá';
                        
                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }

                        //como el stock de Cerdá Kids lo gestionamos directamente del csv cada hora, aquí solo gestionamos la disponibilidad de los productos Lifestyle Adult, cod_target = 5, los demás los dejamos a 0
                        //26/08/2020 Ya no cargamos más que los lifestyle adult, cod_target = 5
                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        $atributo = $desc_talla.' - '.$desc_color;

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, referencia_proveedor_base, id_proveedor, nombre_proveedor, nombre, nombre_ing, descripcion, descripcion_ing, precio, pvp, peso, url_producto, url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5, url_imagen_6, stock, estado, disponibilidad, atributo, cod_familia_cerda, cod_subfamilia_cerda, desc_color_cerda, desc_talla_cerda, cod_target_cerda, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", "'.$referencia_base.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$nombre_ing.'", "'.$descripcion.'", "'.$descripcion_ing.'", '.$precio.', '.$pvp.', '.$peso.', "'.$url_producto.'", "'.$url_imagen.'", "'.$url_imagen_2.'", "'.$url_imagen_3.'", "'.$url_imagen_4.'", "'.$url_imagen_5.'", "'.$url_imagen_6.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$atributo.'", '.$cod_familia.', '.$cod_subfamilia.', "'.$desc_color.'", "'.$desc_talla.'", '.$cod_target.', NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'eri') !== false){
            $proveedor = 'Erik';
            $pattern = '/^ERI_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //16/08/2021 cambio a catálogo descargado de la web
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Erik, si no hay 18 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 18)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 18 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[2]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $descripcion = pSQL(trim($campos[3]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }
                        $descripcion = $descripcion.'<br><br>https://www.grupoerik.com/es/buscar?controller=search&orderby=position&orderway=desc&search_query='.$referencia.'&submit_search=';

                        $ean = trim($campos[4]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $url_imagen = trim($campos[5]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        //el catálogo descargado no tiene stock, solo "Disponible", "Bajo de stock" o "No disponible". Pondremos stock y disponiblilidad en función de ese parámetro
                        $estado = trim($campos[7]);
                        if (!$estado || $estado == '' || is_null($estado)){
                            $estado = 'No disponible';
                        }

                        if ($estado == 'Disponible') {
                            $stock = 1;
                            $disponibilidad = 1;
                        } else if ($estado == 'No disponible') {
                            $stock = 0;
                            $disponibilidad = 0;
                        } else if ($estado == 'Bajo de stock') {
                            $stock = 1;
                            $disponibilidad = 1;
                        } else {
                            $stock = 0;
                            $disponibilidad = 0;
                        }

                        // $stock = number_format(trim($campos[5])); //el stock a veces aparece raro, vienen unidades o solo 0 o 1
                        // if (!$stock || $stock == '' || is_null($stock)){
                        //     $stock = 0;
                        // }

                        $peso = str_replace(',','.',trim($campos[14])); //cambiamos , por .                        
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }

                        //los precios de coste normales, de menos de 1000 euros, vienen en formato 2,34 , es decir, 2€ 34 centimos. Hay que cambiarles la coma por .
                        // pero parece que si el precio es superior a 999, la , pasa a ser divisor de millares y el . es decimal, es decir, 2,230.25 serían 2230€ y 25 centimos.
                        //primero hay que averiguar si el precio contiene un punto, Si es así cambiamos las comas por "", es decir, las eliminamos, y el punto lo dejamos.
                        //si solo tiene coma la cambiamos por punto
                        $precio = trim($campos[15]);
                        if (strpos($precio, '.')) { //si hay un . quitamos la ,
                            $precio = str_replace(',','',$precio);
                        } else { //si no hay punto, cambiamos , por .
                            $precio = str_replace(',','.',$precio);
                        }                                             
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }

                        $fecha_llegada = trim($campos[17]);                        
                        if (!$fecha_llegada || $fecha_llegada == '' || is_null($fecha_llegada)){
                            $fecha_llegada = '0000-00-00';
                        } else {
                            //la fecha llega en formato 12/01/2020, hay que pasarl a 2020-01-12. Si hacemos strtotime() lo pasa a formato americano mm-dd-YY, de modo que cambiamos primero / por -
                            // $fecha_llegada = date("Y-m-d", strtotime(str_replace('/','-',$fecha_llegada)));

                            //16/08/2021 la fecha llega en formato 12-01-2020, hay que pasarl a 2020-01-12. Si hacemos strtotime() lo pasa a formato americano mm-dd-YY, de modo que cambiamos primero / por -
                            $fecha_llegada = date("Y-m-d", strtotime($fecha_llegada));
                        }

                                             
                        $url_producto = 'https://www.grupoerik.com/es/buscar?controller=search&orderby=position&orderway=desc&search_query='.$referencia.'&submit_search=';     
                        
                        //para Erik:
                        $id_proveedor = 8;
                        $nombre_proveedor = 'Erik';

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, url_imagen, stock, estado, disponibilidad, fecha_llegada, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', '.$peso.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$fecha_llegada.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen.' fecha_llegada= '.$fecha_llegada;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'dis') !== false){
            $proveedor = 'Distrineo';
            $pattern = '/^DIS_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Distrineo, si no hay 6 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 6)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 6 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $ean = trim($campos[2]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $atributo = trim($campos[3]);
                        if (!$atributo || $atributo == '' || is_null($atributo)){
                            $atributo = '';
                        }

                        $nombre = pSQL(trim($campos[4]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $stock = trim($campos[5]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }
                                             
                        $url_producto = 'http://www.distrineo.com/pro/recherche.php?motcles='.$referencia;     

                        $descripcion = $url_producto;

                        //la url de imagen se monta con la primera letra de la referencia para la subcarpeta y luego añadiendo _480 a la referencia.
                        $url_imagen = 'http://www.distrineo.com/pro/img/'.substr($referencia,0,1).'/'.$referencia.'_480.jpg';
                        
                        //para Distrineo:
                        $id_proveedor = 121;
                        $nombre_proveedor = 'Distrineo';
                        //como no nos dan precio ponemos uno muy alto para obligar a cambiarlo
                        $precio = 300;
                        
                        if ($stock > 0) {
                            $estado = 'In Stock';
                        } else {
                            $estado = 'No Stock';
                        }

                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, url_producto, url_imagen, stock, estado, disponibilidad, atributo, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$atributo.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'nob') !== false){
            $proveedor = 'Noble Collection';
            $pattern = '/^NOB_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Noble, si no hay 6 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 6)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 6 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        //en Noble, detectamos ean con regexp ya que hay muchos mezclados con referencia. Cuando ean empieza por NN es la referencia, sobretodo para tallas. Si son solo números lo tomamos como ean.
                        $referencia = trim($campos[0]);
                        $ean = trim($campos[2]);

                        if (preg_match('/^N/', $ean)) {
                            $referencia = $ean;
                        }

                        if (!preg_match('/^[0-9]+$/', $ean)) {
                            $ean = '';
                        }
                      
                        $atributo = trim($campos[3]);
                        if (!$atributo || $atributo == '' || is_null($atributo)){
                            $atributo = '';
                        }

                        $nombre = pSQL(trim($campos[4]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $stock = trim($campos[5]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }
                                             
                        $url_producto = 'http://www.noblecollection-distribution.com/pro/recherche.php?motcles='.$referencia;     

                        $descripcion = $url_producto;
                        
                        $url_imagen = 'http://www.noblecollection-distribution.com/pro/img/N/'.$referencia.'_480.jpg';
                        
                        //para Distrineo:
                        $id_proveedor = 111;
                        $nombre_proveedor = 'Noble Collection';
                        //como no nos dan precio ponemos uno muy alto para obligar a cambiarlo
                        $precio = 300;
                        
                        if ($stock > 0) {
                            $estado = 'In Stock';
                        } else {
                            $estado = 'No Stock';
                        }

                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, url_producto, url_imagen, stock, estado, disponibilidad, atributo, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$atributo.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$url_imagen;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'sdd') !== false){
            $proveedor = 'SD Distribuciones';
            $pattern = '/^SDD_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    //sd nos envía el archivo separando campos con tab \t
                    $campos = fgetcsv($handle, 0, "\t");
                     
                    //para SD, si no hay 17 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 17)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 17 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, "\t")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        //03/12/2020 SD nos envía todo su catálogo, que en 12/2020 son 125mil productos, pero los nuevos muchas veces no tienen stock con lo que no se cargan si quitamos los sin stock, así que vamos a admitir todos los con stock y además los que sean del último mes, la fecha de alta es el campo 14
                        
                        //sacamos la fecha que tiene un formato 20201203, si no tuviera pasamos del producto (en principio todos tienen). Comprobamos que el número sea un int de 8 cifras
                        $fechaalta = trim($campos[14]); 
                        if (!$fechaalta || $fechaalta == '' || is_null($fechaalta) || strlen($fechaalta) !== 8){
                            continue;
                        } else {
                            //hacemos create(datetime) from format, con el formato que viene, aaaammdd, es decir Ymd, y al objeto que resulta, le damos formato fecha Y-m-d
                            $fecha = DateTime::createFromFormat('Ymd', $fechaalta);
                            $fecha_llegada = $fecha->format('Y-m-d');
                        }    

                        $stock = trim($campos[16]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            //si no tiene stock comprobamos la fecha                            

                            //sacamos la fecha de hoy y la pasamos a segundos:
                            $hoy_segundos = strtotime(date("Ymd"));
                            //pasamos el campo de fecha alta a formato fecha y luego ese objeto fecha a segundos:
                            $fechaalta_formatofecha = DateTime::createFromFormat('Ymd', $fechaalta);
                            $fechaalta_segundos = strtotime($fechaalta_formatofecha->format('Ymd'));

                            //en un mes hay 2592000 segundos, si restamos a hoy la fecha del campo y la diferencia es superior, lo ignoramos. Si la fecha de alta es futura, el producto aún no ha salido, le damos 15 días, 1296000, es decir, el resultado tiene que estar entre -1296000 y 2592000
                            $segundos_mes = 2592000;
                            $segundos_quincena = -1296000;
                            if ((($hoy_segundos - $fechaalta_segundos) > $segundos_mes) || (($hoy_segundos - $fechaalta_segundos) < $segundos_quincena)) {
                                continue;
                            }     
                            
                            //si no tiene más de 30 dias lo vamos a guardar, ponemos valor 0 a $stock
                            $stock = 0;
                        }   
                        
                        
                        $nombre = pSQL(trim($campos[0]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $ean = trim($campos[1]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }

                        $referencia = trim($campos[2]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $url_imagen = trim($campos[6]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }

                        //los campos 7, 8, 9 y 10 (hay más pero suelen estar vacios) contienen una url de otra imagen secundaria, la url no vale, pero el nombre de la foto es válido, solo consiste en añadir _1, _2 al nombre de la foto principal, luego si el campo no está vacio, creamos la url para las subfotos. Hay que localizar '.jpg' en la url de la imagen principal e insertar _num justo antes
                        $url_imagen_2 = trim($campos[7]);
                        if (!$url_imagen_2 || $url_imagen_2 == '' || is_null($url_imagen_2)){
                            $url_imagen_2 = '';
                        } else {
                            $url_imagen_2 = substr_replace($url_imagen,"_1",stripos($url_imagen,".jpg"),0);
                        }

                        $url_imagen_3 = trim($campos[8]);
                        if (!$url_imagen_3 || $url_imagen_3 == '' || is_null($url_imagen_3)){
                            $url_imagen_3 = '';
                        } else {
                            $url_imagen_3 = substr_replace($url_imagen,"_2",stripos($url_imagen,".jpg"),0);
                        }

                        $url_imagen_4 = trim($campos[9]);
                        if (!$url_imagen_4 || $url_imagen_4 == '' || is_null($url_imagen_4)){
                            $url_imagen_4 = '';
                        } else {
                            $url_imagen_4 = substr_replace($url_imagen,"_3",stripos($url_imagen,".jpg"),0);
                        }

                        $url_imagen_5 = trim($campos[10]);
                        if (!$url_imagen_5 || $url_imagen_5 == '' || is_null($url_imagen_5)){
                            $url_imagen_5 = '';
                        } else {
                            $url_imagen_5 = substr_replace($url_imagen,"_4",stripos($url_imagen,".jpg"),0);
                        }

                        $precio = str_replace(',','.',trim($campos[15])); //cambiamos , por .                        
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }                                        

                        //metemos un punto en el puesto cuatro de la referencia
                        $url_producto = 'http://www.zonalibros.com/Clientes/FichaArticulo.aspx?Libro='.substr_replace($referencia,".",3,0);
                        
                        $descripcion = 'http://www.zonalibros.com/Clientes/FichaArticulo.aspx?Libro='.substr_replace($referencia,".",3,0);
                        
                        //para SD:
                        $id_proveedor = 6;
                        $nombre_proveedor = 'SD Dist.';
                        
                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }
                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, url_producto, url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5, stock, estado, disponibilidad, fecha_llegada, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$url_producto.'", "'.$url_imagen.'", "'.$url_imagen_2.'", "'.$url_imagen_3.'", "'.$url_imagen_4.'", "'.$url_imagen_5.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$fecha_llegada.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' url_imagen '.$url_imagen.' fecha_llegada= '.$fecha_llegada;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'oci') !== false){
            $proveedor = 'Ociostock';
            $pattern = '/^OCI_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos                   
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Ociostock, si no hay 49 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 49)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 49 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        //primero comprobamos stock, si no tiene pasamos al siguiente, ya que nos envían el catálogo completo y son 25mil productos
                        $disponible = trim($campos[14]); 
                        if (!$disponible || $disponible == '' || is_null($disponible)){
                            continue;
                        }                              

                        $nombre = pSQL(trim($campos[3]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $referencia = trim($campos[4]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        //hay problemas con ñ, tildes etc, parece solucionarse así
                        $descripcion = utf8_encode(trim($campos[5]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }  
                        
                        $precio = trim($campos[8]);                        
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }   

                        $stock = trim($campos[15]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }  
                        
                        $url_producto = trim($campos[17]);
                        if (!$url_producto || $url_producto == '' || is_null($url_producto)){
                            $url_producto = '';
                        }

                        $ean = trim($campos[19]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }                        

                        $url_imagen = trim($campos[22]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }      
                        
                        $fecha_llegada = trim($campos[29]);                        
                        if (!$fecha_llegada || $fecha_llegada == '' || is_null($fecha_llegada)){
                            $fecha_llegada = '0000-00-00';
                        } else {
                            //la fecha llega en formato 12/01/2020, hay que pasarl a 2020-01-12. Si hacemos strtotime() lo pasa a formato americano mm-dd-YY, de modo que cambiamos primero / por -
                            $fecha_llegada = date("Y-m-d", strtotime(str_replace('/','-',$fecha_llegada)));
                        }
                        
                        $descripcion = $descripcion.' <br>'.$url_producto;
                        
                        //para ociostock:
                        $id_proveedor = 5;
                        $nombre_proveedor = 'Ociostock';
                        
                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }
                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, url_producto, url_imagen, stock, estado, disponibilidad, fecha_llegada, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$fecha_llegada.'", NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' -ean '.$ean.' -nombre '.$nombre.' -url_producto '.$url_producto.'<br>-stock '.$stock.' -precio '.$precio.' -estado '.$estado.' -url_imagen '.$url_imagen.'<br>-descripcion '.$descripcion.' <br>fecha_llegada ='.$fecha_llegada;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'ext') !== false){
            $proveedor = 'Extended Play';
            $pattern = '/^EXT_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos                   
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Extended Play, si no hay 39 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 39)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 39 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    //comprobamos algunos campos, por el nombre de la cabecera
                    if ((trim($campos[2]) != 'Producto') || (trim($campos[1]) != 'EAN') || (trim($campos[4]) != 'Descripcion larga') || (trim($campos[11]) != 'Precio 1') || (trim($campos[38]) != 'Url imagen')) {
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Las cabeceras de las columnas no coinciden con los campos configurados en importaproveedor.php'; 
                        // $error .= '<br>Producto $campos[2]= "'.trim($campos[2]).'"';
                        // $error .= '<br>EAN $campos[1]= "'.trim($campos[1]).'"';
                        // $error .= '<br>Descripcion larga $campos[4]= "'.trim($campos[4]).'"';
                        // $error .= '<br>Precio 1 $campos[11]= "'.trim($campos[11]).'"';
                        // $error .= '<br>Url imagen $campos[37]= "'.trim($campos[37]).'"';
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        //el catálogo no trae stock, suponemos que el producto que aparece en catálogo es porque tiene stock

                        $nombre = pSQL(trim($campos[2]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }
                        
                        $descripcion = pSQL(trim($campos[4]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }  

                        $ean = trim($campos[1]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }                      

                        $precio = str_replace(',','.',trim($campos[11]));                       
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }   

                        $peso =  str_replace(',','.',trim($campos[15])); //cambiamos , por . y además, muchos no tienen peso, les ponemos 1.111
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }

                        //no viene stock, sino En stock, En transit, Pre order o vacío, y es un poco confuso.                       
                        $stock = 1;
                        
                        //construimos la url del producto en la web según el formato en www.extendedplay.net
                        // https://www.extendedplay.net/es-es/articulo/disco-vinilo-deadpool-2/10006783091/
                        //construimos el nombre pasándolo a minúsculas y sustituyendo espacios por guiones
                        $nombre_url = str_replace(' ','-',strtolower($nombre));

                        $url_producto = 'https://www.extendedplay.net/es-es/articulo/'.$nombre_url.'/'.$referencia;                    

                        //construimos la url de la imagen según el formato en www.extendedplay.net
                        // https://www.extendedplay.net/ClientSite/extendedplay/Imagenes/articulos/10006711022.jpg
                        // $url_imagen = 'https://www.extendedplay.net/ClientSite/extendedplay/Imagenes/articulos/'.$referencia.'.jpg';

                        //07/10/2021 Ahora envían una imagen
                        $url_imagen = trim($campos[38]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            //si viniera vacia intentamos construirla
                            $url_imagen = 'https://www.extendedplay.net/ClientSite/extendedplay/Imagenes/articulos/'.$referencia.'.jpg';
                        }                          
                        
                        $descripcion = $descripcion.' <br>'.$url_producto;
                        
                        //para ociostock:
                        $id_proveedor = 70;
                        $nombre_proveedor = 'Extended Play';

                        //suponemos que si aparece en catálogo está disponible
                        $estado = 'In Stock';
                        $disponibilidad = 1;                       
                        
                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, url_imagen, stock, estado, disponibilidad, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', "'.$peso.'", "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' -ean '.$ean.' -nombre '.$nombre.' -url_producto '.$url_producto.'<br>-stock '.$stock.' -precio '.$precio.' -estado '.$estado.' -url_imagen '.$url_imagen.'<br>-descripcion '.$descripcion.' <br>peso = '.$peso;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'sup') !== false){
            $proveedor = 'Superplay';
            $pattern = '/^SUP_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente  
                    //en este caso, de momento el archivo de Superplay tiene varias líneas vacias, mientras pruebo, me salto las 2 
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                    $campos = fgetcsv($handle, 0, ";");  
                     
                    //para Superplay, si no hay 12 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 12)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 12 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        //el catálogo no trae stock, suponemos que el producto que aparece en catálogo es porque tiene stock

                        $nombre = pSQL(trim($campos[2]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }
                        
                        $descripcion = pSQL(trim($campos[6]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }  

                        $ean = trim($campos[3]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }                      

                        $precio = trim($campos[7]);                       
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }  
                        
                        $pvp = trim($campos[8]); 
                        if (!$pvp || $pvp == '' || is_null($pvp)){
                            $pvp = 0;
                        }    

                        // $peso =  str_replace(',','.',trim($campos[9])); //cambiamos , por . y además, muchos no tienen peso, les ponemos 1.111
                        // if (!$peso || $peso == '' || is_null($peso)){
                        //     $peso = 1.111;
                        // }

                        
                        $stock = trim($campos[4]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }  
                                
                        $url_imagen = trim($campos[10]);
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }         
                        
                        //no tiene url de producto (de momento) Generamos la url del buscador de su web con la referencia de producto como objeto de búsqueda
                        $url_producto = 'https://store.superplay.pt/es/search?q='.$referencia.'&type=product';
                        
                        $descripcion = $descripcion.' <br>'.$url_producto;
                        
                        //para Superplay:
                        $id_proveedor = 137;
                        $nombre_proveedor = 'Superplay';

                        if ($stock > 0) {
                            $estado = 'In Stock';
                            $disponibilidad = 1;
                        } else {
                            $estado = 'No Stock';
                            $disponibilidad = 0;
                        }
                                               
                        
                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, pvp, url_producto, url_imagen, stock, estado, disponibilidad, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', '.$pvp.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' -ean '.$ean.' -nombre '.$nombre.' -url_producto ='.$url_producto.' <br>-stock '.$stock.' -precio '.$precio.' -pvp= '.$pvp.' -estado '.$estado.' -url_imagen '.$url_imagen.'<br>-descripcion '.$descripcion;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

                
        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'glo') !== false){
            $proveedor = 'Globomatik';
            $pattern = '/^GLO_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos                   
                    $campos = fgetcsv($handle, 0, ";");
                     
                    //para Globomatik, si no hay --18 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    //13/09/2021 a día de hoy sacamos archivo con todos productos + url + ficha técnica. 20 columnas! (completo_idiomas_ficha)
                    //04/02/2022 usamos un csv más completo, url https://multimedia.globomatik.net/csv/import.php?username=36979&password=26526848&formato=csv&type=csv10&mode=all
                    // tiene 27 columnas
                    if ((count($campos) != 27)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 27 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    //comprobamos algunos campos, por el nombre de la cabecera
                    if ((trim($campos[1]) !== 'Desc. comercial') || (trim($campos[3]) !== 'Ean') || (trim($campos[10]) !== 'Oferta hasta') || (trim($campos[16]) !== 'Subfamilia') || (trim($campos[20]) !== 'Todas imagenes') || (trim($campos[26]) !== 'Ahorro euros')) {
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Las cabeceras de las columnas no coinciden con los campos configurados en importaproveedor.php'; 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }
                                        
                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        
                        $nombre = pSQL(trim($campos[13]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }
                        
                        //13/09/2021 La descripción va a ser la ficha técnica formateada a lista y debajo lo que viene en descripción, para que lo borren cuando lo pasen a la descripción de arriba.
                        $descripcion = pSQL(trim($campos[2]));
                        if (!$descripcion || $descripcion == '' || is_null($descripcion)){
                            $descripcion = '';
                        }   
                        
                        //ficha técnica es un string con una tabla con clases y formato de la web de globomatik, hay que eliminar tags y clases y dejarlo como una lista para nuestro prestashop.
                        $url_ficha_tecnica = pSQL(trim($campos[12]));

                        if (!$url_ficha_tecnica || $url_ficha_tecnica == '' || is_null($url_ficha_tecnica)){
                            $ficha_tecnica = '';
                        } else {
                            $fileContent = file_get_contents($url_ficha_tecnica);
                            //elimina todo lo que haya dentro de los tags que no sea el nombre del tag. El regex indica dos grupos, lo que hay entre paréntesis. nada más aparecer el primer < coge las letras y números que haya seguidas y forma el grupo $1, ([a-z][a-z0-9]*), luego coge todo lo que haya que no sea > [^>], o hasta que aparece / que es el cierre, y con lo que forma el grupo 2, (\/?). El contenido de toda la etiqeuta lo sustituye por apertura y cierre, con los grupos uno y dos. El grupo dos simplemente es el cierre de tag si es que lo hay. <$1$2>
                            $sinAtributos = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si",'<$1$2>', $fileContent);

                            //elimina todo lo que hay entre <script y script> incluidos, limpiando el javascript.
                            $sinScripts = preg_replace('/<script.*?script>/', '', $sinAtributos);

                            //elimina los tags que no aparezcan en la llamada a función, solo deja por tanto los de tabla que he puesto. Pero si hubiera algún tag con contenido, dejaría el contenido, es decir, no vacía.
                            $sinTags = strip_tags($sinScripts, '<h3><table><tbody><tr><th><td>');

                            $sinEspacios =  preg_replace('/\s\s/', '', $sinTags); //eliminamos espacios en blanco. ponemos dos \s\s para no quitar los espacios entre palabras
                            //sustituimos <tbody> por <ul style="list-style-type:none;">, tr por li, th por b, td por i, y eliminamos el cierre de tabla </table> poniendo salto de línea.  y eliminamos apertura de tabla
                            $sustituciones =  preg_replace('/<tbody>/', "<ul style='list-style-type:none;'>", $sinEspacios); //las comillas tienen que ir así para que se inserte correctamente
                            // $sustituciones =  preg_replace('/<tbody>/', '<ul>', $sinTags);
                            $sustituciones =  preg_replace('/<\/tbody>/', '</ul>', $sustituciones);
                            $sustituciones =  preg_replace('/<tr>/', '<li>', $sustituciones);
                            $sustituciones =  preg_replace('/<\/tr>/', '</li>', $sustituciones);
                            $sustituciones =  preg_replace('/<th>/', '<b>', $sustituciones);
                            $sustituciones =  preg_replace('/<\/th>/', ':</b>', $sustituciones);                            
                            $sustituciones =  preg_replace('/<td>/', '   <i>', $sustituciones); //tres espacios entre el título en negrita y las cursivas
                            $sustituciones =  preg_replace('/<\/td>/', '</i>', $sustituciones);
                            $sustituciones =  preg_replace('/<\/table>/', '<hr><br>', $sustituciones);
                            // $sustituciones =  preg_replace('/<h3>/', '<h2>', $sustituciones);
                            // $sustituciones =  preg_replace('/<\/h3>/', '</h2>', $sustituciones);
                            $sustituciones =  preg_replace('/<table>/', '', $sustituciones);
                            // $ficha_tecnica =  preg_replace('/"/', 'plg', $sustituciones); //cambiamos " (pulgadas) a plg para poder hacer insert
                            $ficha_tecnica =  preg_replace('/"/', '\"', $sustituciones); //cambiamos " (pulgadas)  para poder hacer insert
                        }                        

                        
                        //fin ficha técnica
                        //ponemos ficha técnica como descripción, y le concatenamos la descripción, para que cuando  creen el producto manualmente, lo modifiquen y pongan en su sitio y eliminen de debajo de la ficha técnica.
                        $descripcion = $ficha_tecnica.'<br><hr><br>'.$descripcion;


                        $ean = trim($campos[3]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $ean = '';
                        }   

                        //canon, en caso de tener canon lo sumamos al coste básico
                        $canon = str_replace(',','.',trim($campos[8]));            
                        if (!$canon || $canon == '' || is_null($canon)){
                            $canon = 0;                
                        } 

                        //para poner nuestro coste, calculamos el coste que nos pasan + canon + 2.99 de envío. El coste que nos pasan está en la columna $campos[24] pvd si el producto no tiene descuento, si tiene descuento ahí estará con descuento y tenemos que buscar el coste en $campos[8] Coste anterior. Queremos crear el producto con coste sin descuento, de modo que miramos ambas columnas, si Coste anterior está vacio cogemos el valor de pvd y sumamos canon y envío. Si coste anterior no está vacío, lo cogemos y sumamos canon y envío.
                        //Para crearlo tenemos que ponerle el coste real, sin oferta, que va en el campo $coste_anterior si el producto está en oferta en el momento de sacar el catálogo y en el campo $coste si no está en oferta, al que llamamos coste_real_globo y metemos en coste_original, y para coste de presta pondremos ese coste original + canon + 2.99 de envío
                        
                        $coste = str_replace(',','.',trim($campos[24]));            
                        if (!$coste || $coste == '' || ($coste <= 0) || is_null($coste)){
                            $coste = 0;
                        }

                        $coste_anterior = str_replace(',','.',trim($campos[9]));            
                        if (!$coste_anterior || $coste_anterior == '' || is_null($coste_anterior)){
                            //no hay oferta, $coste corresponde al coste real del producto
                            $coste_real = $coste;                            
                        } else {
                            //hay oferta, $coste corresponde en realidad al coste con oferta del producto, el coste real estará en $coste_anterior
                            $coste_real = $coste_anterior;  
                        }

                        //El coste definitivo es el $coste + $canon + gastos fijos de envío, 2.99
                        $coste_final = $coste_real + $canon + 2.99;     

                        $peso =  trim($campos[21]); 
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        }
                        
                        $stock = trim($campos[23]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }  
                                                
                        //cogemos la url, aunque a veces ha dado problemas y necesitamos login para acceder

                        $url_producto = trim($campos[22]); 
                        if (!$url_producto || $url_producto == '' || is_null($url_producto)){
                            $url_producto = 'https://www.globomatik.com'; 
                        }                     

                        //url de la imagen. En el archivo hay un campo que se llama Todas imágenes donde vienen todas separadas por una coma unas de otras. Las dos últimas siempre son la de baja resolución y la thumb. Queremos hacer explode y sacar las imágenes ignorando las 2 últimas (hasta un máximo de 6). explode con parámetro -2 saca los campos separados, menos los dos últimos.
                        $todas_imagenes = explode(',', trim($campos[20]), -2);
                        //contamos las imágenes resultantes
                        $num_imagenes = count($todas_imagenes);
                        //según el número de imágenes, generamos un string para la sql y otro para los datos del insert. Si son más de 6 se cogen solo 6.
                        if ($num_imagenes == 0) {
                            $sql_imagenes = '';
                            $insert_imagenes = '';
                        } else if ($num_imagenes == 1) {
                            $sql_imagenes = 'url_imagen,';
                            $insert_imagenes = '"'.$todas_imagenes[0].'", ';
                        } else if ($num_imagenes == 2) {
                            $sql_imagenes = 'url_imagen, url_imagen_2,';
                            $insert_imagenes = '"'.$todas_imagenes[0].'", "'.$todas_imagenes[1].'", ';
                        } else if ($num_imagenes == 3) {
                            $sql_imagenes = 'url_imagen, url_imagen_2, url_imagen_3,';
                            $insert_imagenes = '"'.$todas_imagenes[0].'", "'.$todas_imagenes[1].'", "'.$todas_imagenes[2].'", ';
                        } else if ($num_imagenes == 4) {
                            $sql_imagenes = 'url_imagen, url_imagen_2, url_imagen_3, url_imagen_4,';
                            $insert_imagenes = '"'.$todas_imagenes[0].'", "'.$todas_imagenes[1].'", "'.$todas_imagenes[2].'", "'.$todas_imagenes[3].'", ';
                        } else if ($num_imagenes == 5) {
                            $sql_imagenes = 'url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5,';
                            $insert_imagenes = '"'.$todas_imagenes[0].'", "'.$todas_imagenes[1].'", "'.$todas_imagenes[2].'", "'.$todas_imagenes[3].'", "'.$todas_imagenes[4].'", ';
                        } else {
                            $sql_imagenes = 'url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5, url_imagen_6,';
                            $insert_imagenes = '"'.$todas_imagenes[0].'", "'.$todas_imagenes[1].'", "'.$todas_imagenes[2].'", "'.$todas_imagenes[3].'", "'.$todas_imagenes[4].'", "'.$todas_imagenes[5].'", ';
                        }                        
                        
                        //para Globomatik:
                        $id_proveedor = 156;
                        $nombre_proveedor = 'Globomatik';

                        if ($stock > 4) {
                            $estado = 'In Stock';
                        } elseif (($stock <= 4) && ($stock > 0)) {
                            $estado = 'Low Stock';
                        } elseif ($stock <= 0) {
                            $estado = 'No Stock';
                        }
                        if ($stock > 0) {
                            $disponibilidad = 1;
                        } else {
                            $disponibilidad = 0;
                        }                      

                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, '.$sql_imagenes.' stock, estado, disponibilidad, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$coste_final.', '.$peso.', "'.$url_producto.'", '.$insert_imagenes.' '.$stock.', "'.$estado.'", '.$disponibilidad.', NOW())';
                                               
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' -ean '.$ean.' -nombre '.$nombre.' -url_producto '.$url_producto.'<br>-stock '.$stock.' -precio '.$coste_final.' -estado '.$estado.' <br>-descripcion '.$descripcion.' <br>peso = '.$peso.'<br>SQL imagenes= '.$sql_imagenes.'<br>INSERT imagenes = '.$insert_imagenes.'<br>SQL_INSERT= '.$sql_insert_datos;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

        } elseif (strpos(strtolower($explode_nombre_archivo[0]),'dmi') !== false){
            $proveedor = 'DMI';
            $pattern = '/^DMI_[0-9]{2}_[0-1]{1}[0-9]{1}_[0-9]{4}$/';
            if (preg_match($pattern, $explode_nombre_archivo[0], $match)) {
                //Encaja, abrimos el archivo
                if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                    //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente                      
                    //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                    $campos = fgetcsv($handle, 0, ";");
                                         
                    //para DMI, si no hay 28 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                    if ((count($campos) != 28)){
                        
                        $error = '<br>Error en archivo de catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 28 y son '.count($campos); 
                        $output = '<div class="panel">'.$error.'</div>';
                        
                        return $output;
                    }

                    // while (($campos = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    //     $num = count($campos);
                    //     echo "<p> $num fields in line $row: <br /></p>\n";
                    //     $row++;
                    //     for ($c=0; $c < $num; $c++) {
                    //         echo $campos[$c] . "<br />\n";
                    //     }
                    // }

                    $productos = 0;
                    $no_procesados = 0;

                    while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {
                        //asignamos cada columna a su campo en la tabla, nos aseguramos de que haya algo en cada variable para que no falle el insert
                        
                        //primero comprobamos la familia. A 16/11/2021 Para no cargar el catálogo completo solo vamos a coger los que tengan familia Accesorios telefonía, Auriculares, gaming, micrófonos, periféricos o videojuegos. Ese es el campo familia, pero corresponde cada una a un id numérico en la columna Categoría
                        // Accesorios telefonía 999953, Auriculares 999977, Gaming 999956, Micrófonos 999944 , Periféricos 999996 , Videojuegos 2834.
                        $ids_familias = array(999977, 999956, 999944, 999996, 2834, 999953);
                        $id_familia = trim($campos[17]);  
                        if (!$id_familia || $id_familia == '' || is_null($id_familia) || !in_array($id_familia, $ids_familias)){                            
                            $no_procesados++;
                            continue;
                        }

                        $nombre = pSQL(trim($campos[10]));
                        if (!$nombre || $nombre == '' || is_null($nombre)){
                            // $nombre = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }

                        $referencia = trim($campos[0]);  
                        if (!$referencia || $referencia == '' || is_null($referencia)){
                            // $referencia = 'ErrorCSV';
                            $no_procesados++;
                            continue;
                        }
                        
                        $ean = trim($campos[16]);
                        if (!$ean || $ean == '' || is_null($ean)){
                            $no_procesados++;
                            continue;
                        }    

                        $url_producto = trim($campos[11]);
                        if (!$url_producto || $url_producto == '' || is_null($url_producto)){
                            $url_producto = '';
                        }   

                        //cuando el campo solo contiene una palabra en el csv, no va con comillas (si son varias palabras si). Para que coja la tilde ponemos utf8_encode()
                        $familia = utf8_encode(trim($campos[8]));
                        if (!$familia || $familia == '' || is_null($familia)){                            
                            $no_procesados++;
                            continue;
                        }

                        $marca = utf8_encode(trim($campos[9]));
                        if (!$marca || $marca == '' || is_null($marca)){                            
                            $marca = '';
                        }

                        $subfamilia = utf8_encode(trim($campos[19]));
                        if (!$subfamilia || $subfamilia == '' || is_null($subfamilia)){                            
                            $no_procesados++;
                            continue;
                        }

                        $peso = trim($campos[2]); 
                        if (!$peso || $peso == '' || is_null($peso)){
                            $peso = 1.111;
                        } 

                        $canon_precio = trim($campos[4]); 
                        if (!$canon_precio || $canon_precio == 0.00 || $canon_precio == '' || is_null($canon_precio)){
                            $canon_precio = '';
                        } 

                        $canon_tipo = trim($campos[5]); 
                        if (!$canon_tipo || $canon_tipo == '' || is_null($canon_tipo)){
                            $canon_tipo = '';
                        } 

                        //combinación para que respete tildes, dobles comillas dentro del texto, etc
                        $descextrtf = pSQL(utf8_encode(trim($campos[15]))); 
                        if (!$descextrtf || $descextrtf == '' || is_null($descextrtf)){
                            $descextrtf = '';
                        } 

                        //no viene descripción, hago una concatenación de familia, subfamilia, marca, peso y si hay canon y DESCEXTRF
                        $descripcion = "Familia: ".$familia."<br>Subfamilia: ".$subfamilia."<br>Marca: ".$marca."<br>Peso: ".$peso." kg";
                        if ($canon_precio !== '') {
                            $descripcion .= "<br>Canon: ".$canon_precio." €<br>Tipo canon: ".$canon_tipo;
                        }
                        if ($descextrtf !== '') {
                            $descripcion .= "<br>Otra info: ".$descextrtf;
                        }

                        $precio = trim($campos[6]);                       
                        if (!$precio || $precio == '' || is_null($precio)){
                            $precio = 0;
                        }  

                        $stock = trim($campos[12]); 
                        if (!$stock || $stock == '' || is_null($stock)){
                            $stock = 0;
                        }  
                                
                        //algunas imagenes contienen caracteres raros ( º )
                        $url_imagen = pSQL(utf8_encode(trim($campos[25])));
                        if (!$url_imagen || $url_imagen == '' || is_null($url_imagen)){
                            $url_imagen = '';
                        }         
                        
                        //para MDI:
                        $id_proveedor = 160;
                        $nombre_proveedor = 'DMI';

                        if ($stock > 0) {
                            $estado = 'In Stock';
                            $disponibilidad = 1;
                        } else {
                            $estado = 'No Stock';
                            $disponibilidad = 0;
                        }                                               
                        
                        //introducimos la línea en frik_aux_import_catalogos
                        $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                        (ean, referencia_proveedor, id_proveedor, nombre_proveedor, nombre, descripcion, precio, peso, url_producto, url_imagen, stock, estado, disponibilidad, date_add) VALUES 
                        ("'.$ean.'", "'.$referencia.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$descripcion.'", '.$precio.', '.$peso.', "'.$url_producto.'", "'.$url_imagen.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', NOW())';
    
                        if (!Db::getInstance()->execute($sql_insert_datos)){
                            $mensaje .= '<br><br>Error con referencia '.$referencia.' -ean '.$ean.' -nombre '.$nombre.' -url_producto ='.$url_producto.' <br>-stock '.$stock.' -precio '.$precio.' -estado '.$estado.' -url_imagen '.$url_imagen.'<br>-descripcion '.$descripcion;

                            $no_procesados++;
                        } else {
                            $productos++;
                        }
                    }                 
                    
                    fclose($file);
                    
                
                } else {
                    $mensaje .= '<br>No pude abrir el archivo';
                    $output = '<div class="panel">'.$mensaje.'</div>';
                                
                    return $output;
                    
                }

                $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;
                $output = '<div class="panel">'.$mensaje.'</div>';
                
                                
                return $output;
                

            } else {
                $error = 'El nombre del archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados.';

                $output = '<div class="panel">'.$error.'</div>';
                                
                return $output;
            } 

                
        } elseif ($archivo_escogido == '102679_2.csv'){
            //con Cerdá, desde 06/08/2021 comienzo a procesar el segundo archivo de catálogo, el que contiene productos con y sin stock. Ni le cambio el nombre.
            //Lo ideal es meter este archivo e importarlo, actualizar las tablas solo con este, para que meta al importador los productos y después cargar el archivo horario, no antes de vaciar aux_import_catalogso, para que no haya productos repetidos
            //primero se saca el campo Año, si es inferior a 2021 se pasa al siguiente. Si no, se compara línea a línea con frik_catalogo_cerda_crear. Si está se pasa al siguiente, si no, se mete en la tabla, comprobando si existe la referencia o ean en prestashop (en cuyo caso habría que ignorarlo) y si además es Lifestyle adult se mete también en frik_aux_import_catalogos para llevarlo a frik_import_catalogos.  
            $proveedor = 'Cerdá';                    
            
            if (($handle = fopen($path.$archivo_escogido, "r")) !== FALSE) {
                //la primera línea no la procesamos con el resto ya que es la cabecera de la tabla, pero examinamos su número de campos para ver si coincide con los que debería tener normalmente   
                //fgetcsv directamente hace "explode" a un array, mejor que fget(), además parece respetar los caracteres como &#39; y no se cuelan los ; partiendo los campos
                $campos = fgetcsv($handle, 0, ";");
                    
                //para el catálogo sin stock de Cerdá, si no hay 44 campos es que hay algún error o cambio en el archivo, no lo procesamos y mostramos error
                if ((count($campos) != 44)){
                    
                    $error = '<br>Error en archivo de Cerdá catálogo '.$archivo_escogido.' . Error en número de columnas, no coincide, deberían ser 44 y son '.count($campos); 
                    $output = '<div class="panel">'.$error.'</div>';
                    
                    return $output;
                }

                $productos = 0;
                $no_procesados = 0;     
                $anteriores_2021 = 0;
                $error_ean = 0;                
                $insertados_catalogo_cerda = 0;
                $insertados_import_catalogos = 0;
                $error_cod_target = 0;
                $error_coste = 0;
                
                while (($campos = fgetcsv($handle, 0, ";")) !== FALSE) {   
                    //primero comprobamos Año, si es inferior a 2021 pasamos al siguiente
                    $year = trim($campos[11]);
                    if ($year < 2021){
                        $anteriores_2021++;
                        continue;
                    } 

                    //10/08/2021 Si coste es 0 pasamos al siguiente, ya que no podemos crear un producto sin saber lo que nos cuesta
                    $coste = trim($campos[39]);
                    if (($coste == 0) || !($coste > 0)) {
                        $error_coste++;
                        continue;
                    }

                    //ahora comprobamos si el ean es correcto. Si no hay ean  pasamos al siguiente
                    $ean = trim($campos[21]);
                    if (!$ean || $ean == '' || is_null($ean) || !Validate::isEan13($ean)){
                        $error_ean++;
                        continue;
                    } else {
                        //obtenemos aquí cod_target y comprobamos que sea 1 a 5 (a veces no tiene o viene una x...) si es lifestyle adult(5) lo meteremos en ambas tablas, si es kids (1-4) solo en frik_catalogo_cerda_crear     
                        $cod_target = trim($campos[42]);
                        if (!in_array($cod_target, array(1, 2, 3, 4, 5))){
                            $error_cod_target++;
                            continue;
                        }
                                                
                        //asignamos el valor de cada campo a su variable correspondiente. Sacamos los que son campos de la tabla frik_catalogo_cerda y en parecido orden. 
                        $surtido = trim($campos[1]);
                        if (!$surtido || $surtido == '' || is_null($surtido)){
                            $surtido = '';
                        }
                        $referencia = trim($campos[0]); 
                        $nombre = trim($campos[2]);
                        $nombre_ing = trim($campos[3]);
                        $cod_familia = trim($campos[4]);
                        $familia = trim($campos[5]);
                        $cod_subfamilia = trim($campos[7]);
                        $subfamilia = trim($campos[8]);
                        $campana = trim($campos[10]);
                        $temporada = trim($campos[11]);
                        $composicion = trim($campos[12]);
                        $personaje = trim($campos[14]);
                        $medida_gen = trim($campos[22]);
                        $peso = trim($campos[25]);
                        $imagen = trim($campos[26]);
                        $imagen_2 = trim($campos[27]);
                        $imagen_3 = trim($campos[28]);
                        $imagen_4 = trim($campos[29]);
                        $imagen_5 = trim($campos[30]);
                        $imagen_6 = trim($campos[31]);
                        $gif = trim($campos[34]);
                        $video = trim($campos[35]);            
                        $desc_color = trim($campos[32]);            
                        $desc_talla = trim($campos[33]);
                        $clasificacion = trim($campos[36]);                        
                        $pvp = trim($campos[37]);
                        $descripcion = trim($campos[40]);            
                        $desc_target = trim($campos[43]);

                        //buscamos ean en catalogo cerdá
                        $sql_existe_ean = 'SELECT id_catalogo_cerda_crear
                            FROM frik_catalogo_cerda_crear 
                            WHERE ean = "'.$ean.'"';
                        $existe_ean = Db::getInstance()->ExecuteS($sql_existe_ean);

                        if (count($existe_ean) == 0) {  
                            //no está en tabla.                             
                            //primero comprobamos si tiene referencia producto base, es decir, surtido, ya que si es una combinación, podría no estar en la tabla pero existir ya el producto base                    
                            
                            $referencia_prestashop = '';
                            $id_product_prestashop = 0;
                            $existe_surtido_cerda = 0;
                            if ($surtido && $surtido != ''){
                                $sql_existe_surtido = 'SELECT id_catalogo_cerda_crear, id_product_prestashop, referencia_prestashop, antiguo
                                FROM frik_catalogo_cerda_crear 
                                WHERE existe_prestashop = 1
                                AND surtido = "'.$surtido.'"';
                                $existe_surtido = Db::getInstance()->ExecuteS($sql_existe_surtido);
                                if (count($existe_surtido) > 0) {    
                                    //el producto surtido existe, comprobamos que ya existe en prestashop, si es así sacamos la referencia para insertarla también al meter los datos a frik_catalogo_cerda_crear                            
                                    if ($id_product_prestashop = $existe_surtido[0]['id_product_prestashop']){
                                        $existe_surtido_cerda = 1;
                                        $referencia_prestashop = $existe_surtido[0]['referencia_prestashop'];
                                    }                            

                                }
                            }

                            //Si el producto es Lifestyle Adult, lo introducimos con eliminado = 1 para asegurarnos de que no lo creará el proceso de crear productos, ya que esos los meterán manualmente en prestashop.                                
                            $eliminado = 0;
                            if (($cod_target == 5)) {
                                $eliminado = 1;
                            }
                            
                            $sql_insert_datos = 'INSERT INTO frik_catalogo_cerda_crear
                            (ean, referencia, surtido, nombre, nombre_ing, cod_familia, familia, cod_subfamilia, subfamilia, composicion, personaje,
                            medida_gen, peso, imagen, imagen_2, imagen_3, imagen_4, imagen_5, imagen_6, gif, video, desc_color,
                            desc_talla, clasificacion, coste, pvp, descripcion, cod_target, desc_target, referencia_prestashop, eliminado, date_add) VALUES 
                            ("'.$ean.'", "'.$referencia.'", "'.$surtido.'", "'.$nombre.'", "'.$nombre_ing.'", '.$cod_familia.', "'.$familia.'", '.$cod_subfamilia.', "'.$subfamilia.'", "'.$composicion.'", "'.$personaje.'", "'.$medida_gen.'", '.$peso.', "'.$imagen.'", "'.$imagen_2.'", "'.$imagen_3.'", "'.$imagen_4.'", "'.$imagen_5.'", "'.$imagen_6.'", "'.$gif.'", "'.$video.'", "'.$desc_color.'", "'.$desc_talla.'", "'.$clasificacion.'", '.$coste.', '.$pvp.', "'.$descripcion.'", '.$cod_target.', "'.$desc_target.'", "'.$referencia_prestashop.'", '.$eliminado.', NOW())';
                            
                            if (!Db::getInstance()->execute($sql_insert_datos)){
                                $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.'<br>coste '.$coste.' atributo '.$atributo.' url_imagen '.$imagen;

                                $no_procesados++;
                            } else {
                                $insertados_catalogo_cerda++;
                            }
                        } 

                        //una vez metido en catalogo cerdá, miramos si es adult de nuevo, si lo es lo metemos en aux_import_catalogos, luego el proceso que pasa de esa tabla a import_catalogos hará su trabajo. Da igual la disponibilidad, porque se gestiona también con el catálogo horario.
                        if ($cod_target == 5){   
                            //a descripcion le añadimos algunos datos para ayudar en la creación manual del producto
                            $descripcion = $descripcion.' <br><br>'.$composicion.' <br><br>'.$personaje.' <br><br>'.$desc_color.' <br><br>'.$desc_talla.' <br><br>'.$medida_gen;

                            $descripcion_ing = $nombre_ing.' <br><br>'.$composicion.' <br><br>'.$personaje.' <br><br>'.$desc_color.' <br><br>'.$desc_talla.' <br><br>'.$medida_gen;

                            if ($surtido) {
                                $url_producto = 'https://www.cerdagroup.com/es/product/show/'.$surtido.'/'.str_replace(' ','-', $nombre).'/';
                            } else {
                                $url_producto = 'https://www.cerdagroup.com/es/product/show/'.$referencia.'/'.str_replace(' ','-', $nombre).'/';
                            }

                            //para Cerdá:
                            $id_proveedor = 65;
                            $nombre_proveedor = 'Cerdá';

                            //al entrar en este catálogo no sabemos si tienen stock, así que lo dejamos como si no tuviera
                            $stock = 0;
                            $estado = 'No Stock';
                            $disponibilidad = 0;                                    

                            $atributo = $desc_talla.' - '.$desc_color;

                            //introducimos la línea en frik_aux_import_catalogos
                            $sql_insert_datos = 'INSERT INTO frik_aux_import_catalogos
                            (ean, referencia_proveedor, referencia_proveedor_base, id_proveedor, nombre_proveedor, nombre, nombre_ing, descripcion, descripcion_ing, precio, pvp, peso, url_producto, url_imagen, url_imagen_2, url_imagen_3, url_imagen_4, url_imagen_5, url_imagen_6, stock, estado, disponibilidad, atributo, cod_familia_cerda, cod_subfamilia_cerda, desc_color_cerda, desc_talla_cerda, cod_target_cerda, date_add) VALUES 
                            ("'.$ean.'", "'.$referencia.'", "'.$surtido.'", '.$id_proveedor.', "'.$nombre_proveedor.'", "'.$nombre.'", "'.$nombre_ing.'", "'.$descripcion.'", "'.$descripcion_ing.'", '.$coste.', '.$pvp.', '.$peso.', "'.$url_producto.'", "'.$imagen.'", "'.$imagen_2.'", "'.$imagen_3.'", "'.$imagen_4.'", "'.$imagen_5.'", "'.$imagen_6.'", '.$stock.', "'.$estado.'", '.$disponibilidad.', "'.$atributo.'", '.$cod_familia.', '.$cod_subfamilia.', "'.$desc_color.'", "'.$desc_talla.'", '.$cod_target.', NOW())';

                            if (!Db::getInstance()->execute($sql_insert_datos)){
                                $mensaje .= '<br><br>Error con referencia '.$referencia.' ean '.$ean.' nombre '.$nombre.' url_producto '.$url_producto.'<br>stock '.$stock.' precio '.$precio.' atributo '.$atributo.' url_imagen '.$imagen;

                                $no_procesados++;                                    
                            } else {
                                $insertados_import_catalogos++;
                            }           
                            
                        } //si no es adult no hay que hacer más, pasamos al siguiente
                        
                    }     
                }                
                
                fclose($file);
                
            
            } else {
                $mensaje .= '<br>No pude abrir el archivo';
                $output = '<div class="panel">'.$mensaje.'</div>';
                            
                return $output;
                
            }

            // $mensaje .= '<br>Archivo procesado - Productos añadidos '.$productos.' - Productos no procesados '.$no_procesados;

            $mensaje .= '<br>Archivo procesado                     
                    <br>- Productos anteriores a 2021 = '.$anteriores_2021.'
                    <br>- Productos con error en ean = '.$error_ean.'  
                    <br>- Productos con coste 0 = '.$error_coste.'                  
                    <br>- Productos con error en cod_target = '.$error_cod_target.'
                    <br>- Productos insertados_catalogo_cerda = '.$insertados_catalogo_cerda.'                    
                    <br>- Productos insertados aux_import_catalogos = '.$insertados_import_catalogos.'
                    <br>- Productos con error al insertar = '.$no_procesados;    

            $output = '<div class="panel">'.$mensaje.'</div>';
            
                            
            return $output;

        } else {            
            $error = 'El archivo '.$archivo_escogido.' no tiene el formato correspondiente a los archivos de catálogos soportados o su nombre no se reconoce.';

            $output = '<div class="panel">'.$error.'</div>';
                            
            return $output;
        }
        

//SUBIMOS MEDIANTE SQL directamente, esto de debajo para otra vez...
        //utilizando una carpeta normal sacamos los datos del csv con php y hacemos insert a frik_import_catalogos por cada línea
        //primero mostramos las 20 primeras líneas del catálogo
        // $explode_nombre_archivo = explode(".",$archivo_escogido);
        // $es_catalogo = 0; 
        // if (strpos(strtolower($explode_nombre_archivo[0]),'red') !== false){
        //     $proveedor = 'Redstring';
        //     $es_catalogo = 1;          

        // } elseif (strpos(strtolower($explode_nombre_archivo[0]),'aby') !== false){
        //     $proveedor = 'Abysse';
        //     $es_catalogo = 1; 
        // } elseif (strpos(strtolower($explode_nombre_archivo[0]),'kar') !== false){
        //     $proveedor = 'Karactermanía';
        //     $es_catalogo = 1; 
        // } elseif (strpos(strtolower($explode_nombre_archivo[0]),'heo') !== false){
        //     $proveedor = 'Heo';
        //     $es_catalogo = 1; 
        // } 
        
        // if ($es_catalogo !== 1) {
        //     $contenido .= 'El archivo '.$archivo_escogido.' no corresponde a ninguno de los catálogos soportados.';
        // } else {

        //     $directorio = _PS_ROOT_DIR_.'/tmpAuxiliar/';
        //     $ruta_archivo = $directorio.$archivo_escogido;
        //     $handle = fopen($ruta_archivo, "r");

        //     $contenido .= '<form>'; 
        //     $contenido .= '<table class="table"><caption>Contenido de <h1>'.$archivo_escogido.'</h1><br><h2>Catálogo '.$proveedor.'</h2></caption><tbody>';

        //     //si el catálogo es Karactermanía no lleva cabeceras, se las ponemos
        //     // if (($proveedor == 'Karactermanía')){                        
        //     //     $contenido .= '<th>Referencia del artículo</th><th>Descripción (título)</th><th>EAN</th><th>Precio</th><th>Foto</th><th>Familia</th><th>Clase</th><th>Formato</th><th>En Blanco</th><th>Detalle artículo 1</th><th>Detalle artículo 2</th><th>Detalle artículo 3</th><th>Material del bolso</th><th>PVPR ES</th><th>PVPR FR</th><th>PVPR IT</th><th>PVPR DE</th><th>PVPR UK</th><th>Outlet (S/N)</th><th>Stock</th>';
        //     // }

        //     $sombreado = 0;

        //     //while(!feof($handle)) {  
        //     //mostramos solo 10 líneas
        //     while($sombreado < 10) {                      
        //         //fgets() va leyendo cada línea del archivo
        //         //parece que feof() permite un loop extra, ponemos la condición de que fgets() encuentre algo para seguir.
        //         if ($linea = fgets($handle)){                    
        //             $campos = explode(';', $linea);

        //             $num_campos = count($campos);
        //             //$num_campos = 3;          
                    
        //             //si es la primera línea metemos los select para elegir a que corresponde cada columna respecto a la tabla frik_import_catalogos
        //             if ($sombreado == 0){
        //                 $contador = 0; 
        //                 $contenido .= '<tr>';               
        //                 while ($contador < $num_campos){
        //                     $contenido .= '<td>
        //                     <select>
        //                         <option value="nada">-</option>
        //                         <option value="nombre">Nombre</option>
        //                         <option value="referencia_prov">Referencia Proveedor</option>
        //                         <option value="ean">Ean</option>
        //                         <option value="descripcion">Descripción</option>
        //                         <option value="stock">Stock</option>
        //                         <option value="atributo">Atributo</option>
        //                         <option value="coste">Precio</option>
        //                         <option value="url_imagen">URL Imagen</option>
        //                         <option value="url_producto">URL Producto</option>
        //                         <option value="peso">Peso</option>
        //                         <option value="fecha">Fecha eta</option>
        //                     </select> 
        //                     </td>';
        //                     $contador++;
        //                 }
        //                 $contenido .= '</tr>';  
        //             }
                    
        //             $contenido .= '<tr ';
        //             if ($sombreado % 2 !== 0) {
        //                 $contenido .= ' class="sombreada">';
        //             }else{
        //                 $contenido .= '>';    
        //             }
                    
        //             $contador = 0;                
        //             while ($contador < $num_campos){
        //                 if ($sombreado == 0){   
        //                     if ($proveedor == 'Karactermanía'){
        //                         $casilla = trim($campos[$contador]);
        //                         $contenido .= '<td>'.$casilla.'</td>';
        //                     } else {
        //                         $casilla = trim($campos[$contador]);
        //                         $contenido .= '<th>'.$casilla.'</th>';
        //                     }                            
        //                 } else {
        //                     $casilla = trim($campos[$contador]);
        //                     $contenido .= '<td>'.$casilla.'</td>';
        //                 }
        //                 $contador++;
        //             }
        //             $contenido .= '</tr>';
        //             $sombreado++;
            
        //         }
                
        //     }
        //     fclose($ruta_archivo);            
        //     $contenido .= '</tbody></table>';
        //     $contenido .= '</form>'; 
        // }

        
        $output = '<div class="panel">'.$contenido.'</div>';
        
        return $output;

    }
    /*
        Esta función está pensada para ejecutarse después de actualizar los catálogos. Buscará todos los productos que no tienen marcado permitir pedido y, teniendo ciertas características, están disponibles en dichos catálogos. Les marcará permitir pedido, asignando el proveedor por defecto, recatalogando si es posible, etc
        Primero analizará también los productos que, teniendo marcado permitir pedido, corresponden a los proveedores de la tabla frik_import_catalogos y han dejado de estar disponibles o ya no aparecen en los catálogos, y les quitará Permitir pedido

        //17/08/2020 Temporalmente hacemos que este proceso ignore a los productos de Cerdá Kids, ya que por sus características los vamos a procesar aparte
    */
    protected function postProcessAsignarPermitirPedido() 
    {
        $tabla = '<table class="table"><tbody>';
        $tabla .= '<tr><th>ID Producto</th><th>Referencia Producto</th><th>EAN</th><th>Referencia Proveedor</th><th>Proveedor</th><th>Motivo</th></tr>';

        //Sacar los proveedores disponibles en la tabla frik_import_catalogos para crear la consulta
        $sql_proveedores_en_tabla = 'SELECT DISTINCT id_proveedor 
        FROM frik_import_catalogos';
        $proveedores_en_tabla = Db::getInstance()->ExecuteS($sql_proveedores_en_tabla);

        if (!$proveedores_en_tabla || empty($proveedores_en_tabla)){
            $output = '<div class="panel">NO SE ENCUENTRAN PROVEEDORES EN LA TABLA</div>';
            
            return $output;
        }
        $sql_proveedores = '';
        foreach ($proveedores_en_tabla AS $proveedor_en_tabla){
            $id_proveedor_en_tabla = $proveedor_en_tabla['id_proveedor'];
            if (!$sql_proveedores){
                //primera iteración, $sql_proveedores está vacia
                $sql_proveedores .= '(psu.id_supplier = '.$id_proveedor_en_tabla.' AND pro.id_supplier = '.$id_proveedor_en_tabla.' AND ica.id_proveedor = '.$id_proveedor_en_tabla.')';
            }else{
                $sql_proveedores .= ' OR (psu.id_supplier = '.$id_proveedor_en_tabla.' AND pro.id_supplier = '.$id_proveedor_en_tabla.' AND ica.id_proveedor = '.$id_proveedor_en_tabla.')';
            }            
        }  
        
        //Analizamos los productos que SI tienen Permitir Pedido
        //Primero los que tienen Permitir Pedido, aparecen en los catálogos pero ya no están disponibles
        //sacar los ids de los productos que tienen check de permitir pedido, son de HEO (ids 4 ), Abysse (ids 14), Redstring (ids 24), Karactermanía (ids 53), Grupo Erik (ids 8) ignorando atributos para que no salgan repeticiones, que no estén en la categoría prepedido (id_category=121), y que en el catálogo correspondiente ya no aparecen como disponibles inmediatamente (ica.disponible != 1)
        //17/08/2020 Temporalmente hacemos que este proceso ignore a los productos de Cerdá Kids, ya que por sus características los vamos a procesar aparte, por eso quitamos los que tengan id_manufacturer = 76
        //07/10/2020 Temporalmente hacemos que este proceso ignore a los productos de Cerdá Adult, ya que por sus características los vamos a procesar aparte, por eso quitamos los que tengan id_manufacturer = 81
        //22/11/2021 Hacemos que este proceso ignore a los productos de DMI, ya que por sus características los vamos a procesar aparte, pero los mantenemos en la tabla de catálogos para poder crearlos con el importador, por eso quitamos los que tengan id_supplier = 160
        //10/02/2022 Hacemos que este proceso ignore a los productos de Globomatik, ya que por sus características los vamos a procesar aparte, pero los mantenemos en la tabla de catálogos para poder crearlos con el importador, por eso quitamos los que tengan id_supplier = 156
        $sql_productos_en_catalogo = 'SELECT  DISTINCT pro.id_product AS idproducto, pro.reference AS referencia_presta, pro.ean13 AS ean, pro.id_supplier AS idsupplier, psu.product_supplier_reference AS ref_proveedor, ica.eliminado AS eliminado
        FROM lafrips_product pro 
        JOIN lafrips_stock_available ava ON pro.id_product = ava.id_product 
        JOIN lafrips_product_supplier psu ON psu.id_product = pro.id_product
        JOIN frik_import_catalogos ica ON ica.referencia_proveedor = psu.product_supplier_reference 
        WHERE ava.out_of_stock = 1 
        AND ava.id_product_attribute = 0 
        AND ica.disponibilidad != 1
        AND pro.id_manufacturer != 76
        AND pro.id_manufacturer != 81
        AND pro.id_supplier NOT IN (156, 160)
        AND ('.$sql_proveedores.') 
        AND pro.id_product NOT IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 121);
        ';

        $productos_en_catalogo = Db::getInstance()->ExecuteS($sql_productos_en_catalogo);  
        
        $numeroproductosnopermitir = 0;

        //para cada id quitamos Permitir Pedido
        foreach ($productos_en_catalogo as $producto) {
            //Marcamos permitir pedidos (0 no permitir, 1 permitir, 2 por defecto no permitir)
            StockAvailable::setProductOutOfStock($producto['idproducto'], 2);
            $numeroproductosnopermitir++;

            //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname;
            $id_product = $producto['idproducto'];
            $referencia_producto = $producto['referencia_presta'];
            $ean = $producto['ean'];
            $referencia_proveedor = $producto['ref_proveedor'];
            $id_proveedor = $producto['idsupplier'];
            $nombre_proveedor = Supplier::getNameById($id_proveedor);
            $eliminado = $producto['eliminado'];
            //el tipo de operación según si eliminado es 1 o no
            $operacion = '';
            if ($eliminado){
                $operacion = 'Eliminado de catálogo';
            } else {
                $operacion = 'Sin stock en catálogo';
            }

            Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                 (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                 VALUES ('Quitar Permitir - ".$operacion."',".$id_product.",'".$referencia_producto."','".$ean."','".$referencia_proveedor."',".$id_proveedor.",
                        '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");    

            $tabla .= '<tr><td>'.$id_product.'</td><td>'.$referencia_producto.'</td><td>'.$ean.'</td><td>'.$referencia_proveedor.'</td><td>'.$nombre_proveedor.'</td><td>'.$operacion.'</td></tr>';          

        }

        //Segundo, quitamos permitir pedido a los productos que lo tienen marcado y han desaparecido del catálogo de proveedor
        //10/06/2020 esta parte la podemos quitar ya que desde hace meses los productos que no aparecen en un nuevo catálogo se marcan como eliminado = 1 en lugar de borrarlos, luego esta consulta no daría resultados
        //05/11/2020 Recupero la consulta porque al eliminarse entradas en frik_import_catalogos por accidente, quedan productos con permitir pedido que no se les quita nunca ya que al no estar en la tabla no se comprueba si tienen eliminado 1 o no, de modo que, como medida de seguridad, dejamos el proceso, adpatándolo a Cerdá etc
        //22/11/2021 Ignoramos también productos DMI 'AND pro.id_supplier != 160'
        //17/03/2022 Ignoramos también productos Globomatik 'AND pro.id_supplier != 156'

        $sql_proveedores = '';
        foreach ($proveedores_en_tabla AS $proveedor_en_tabla){
            $id_proveedor_en_tabla = $proveedor_en_tabla['id_proveedor'];
            if (!$sql_proveedores){
                $sql_proveedores .= '(pro.id_supplier = '.$id_proveedor_en_tabla.' AND psu.product_supplier_reference NOT IN (SELECT referencia_proveedor FROM frik_import_catalogos WHERE id_proveedor = '.$id_proveedor_en_tabla.'))';
            }else{
                $sql_proveedores .= ' OR (pro.id_supplier = '.$id_proveedor_en_tabla.' AND psu.product_supplier_reference NOT IN (SELECT referencia_proveedor FROM frik_import_catalogos WHERE id_proveedor = '.$id_proveedor_en_tabla.'))';
            }            
        }  
        
        $sql_productos_no_en_catalogo = 'SELECT DISTINCT pro.id_product AS idproducto, pro.reference AS referencia_presta, pro.ean13 AS ean, pro.id_supplier AS idsupplier, psu.product_supplier_reference AS ref_proveedor
        FROM lafrips_product pro
        JOIN lafrips_product_supplier psu ON psu.id_product = pro.id_product
        JOIN lafrips_stock_available ava ON pro.id_product = ava.id_product
        WHERE ava.out_of_stock = 1
        AND ava.id_product_attribute = 0
        AND psu.id_product_attribute = 0
        AND pro.id_supplier = psu.id_supplier
        AND pro.id_manufacturer != 76
        AND pro.id_manufacturer != 81
        AND pro.id_supplier NOT IN (156, 160)        
        AND ('.$sql_proveedores.')         
        AND pro.id_product NOT IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 121);';

        $productos_no_en_catalogo = Db::getInstance()->ExecuteS($sql_productos_no_en_catalogo);

        foreach ($productos_no_en_catalogo as $producto) {
            //Marcamos permitir pedidos (0 no permitir, 1 permitir, 2 por defecto no permitir)
            StockAvailable::setProductOutOfStock($producto['idproducto'], 2);
            $numeroproductosnopermitir++;

            //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname;
            $id_product = $producto['idproducto'];
            $referencia_producto = $producto['referencia_presta'];
            $ean = $producto['ean'];
            $referencia_proveedor = $producto['ref_proveedor'];
            $id_proveedor = $producto['idsupplier'];
            $nombre_proveedor = Supplier::getNameById($id_proveedor);
            Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                 (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                 VALUES ('Quitar Permitir - Fuera de Catálogo&tabla',".$id_product.",'".$referencia_producto."','".$ean."','".$referencia_proveedor."',".$id_proveedor.",
                        '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());");  


            $tabla .= '<tr><td>'.$id_product.'</td><td>'.$referencia_producto.'</td><td>'.$ean.'</td><td>'.$referencia_proveedor.'</td><td>'.$nombre_proveedor.'</td><td>Fuera de frik_import_catalogos</td></tr>'; 
        }
         

        if ($numeroproductosnopermitir == 0){
            $numero = 'Ningún producto';
        } else {
            $numero = $numeroproductosnopermitir.' producto/s';
        }
        $tabla .= '</tbody><caption>Quitado Permitir Pedido - '.$numero.'</caption></table><br><br>';
        $tabla .= '<table class="table"><tbody>';
        

        //Ahora sacamos los productos a los que asignar permitir pedidos
        //sacamos los id de proveedor que están configurados para permitir pedido y creamos el "array" para la consulta de mysql
        $proveedores_permitir_json = Configuration::get('IMPORTA_PROVEEDOR_PROVEEDORES');
        $proveedores_tabla = json_decode($proveedores_permitir_json);
        $lista_proveedores = implode(',', $proveedores_tabla);
        if (!$lista_proveedores){
            //si no hay proveedores configurados para permitir, salimos mostrando solo los productos a los que se han quitado permitir pedido
            $tabla .= '</tbody><caption>NINGÚN PROVEEDOR CONFIGURADO PARA PERMITIR PEDIDO</caption></table><br><br>';
            $tabla .= '</table>';
            
            $output = '<div class="panel">'.$tabla.'</div>';
            
            return $output;
        }

        //Comprobamos la configuración del módulo, sacamos la antigüedad y precio límite configurada
        if (Configuration::get('IMPORTA_PROVEEDOR_PRECIO_LIMITE') !== null){
            $pvp_limite = Configuration::get('IMPORTA_PROVEEDOR_PRECIO_LIMITE');
        }else{
            $pvp_limite = 0;
        }

        if (Configuration::get('IMPORTA_PROVEEDOR_ANTIGUEDAD') !== null){
            $antiguedad_limite = Configuration::get('IMPORTA_PROVEEDOR_ANTIGUEDAD');
        }else{
            $antiguedad_limite = 0;
        }       
        
        $tabla .= '<tr><th>ID Producto</th><th>Referencia Producto</th><th>EAN</th><th>Referencia Proveedor</th><th>Proveedor</th><th>Recatalogado</th><th>Cambio Coste</th><th>Comprobar Peso</th></tr>';

        // buscamos los productos que:
        // -no tienen stock
        // -no tienen permitir pedido
        // -no tengan atributos
        // -tienen menos de $antiguedad_limite días o, si son más viejos, se venden a más de $pvp_limite
        // -no estén en Outlet (id 319) y no tengan descuento, salvo que el descuento sea menos de 20%, para los productos caros a los que se les promociona con un descuento
        //no tengan la categoría No permitir Pedido id 2440
        // -tengan como proveedor alguno de los de la tabla frik_import_catalogos que están configurados para permitir pedido y estén disponibles en el catálogo
        //Si el producto está descatalogado se comprobará si tenemos sus categorías guardades en la tabla frik_aux_category_product, si lo está lo recatalogamos, si no, aparecerá después como producto descatalogado con stock 
        //Si el precio de proveedor es diferente en más de un 10% se cambiará por el nuevo precio
        //si no tuviera peso, se le pone 1.111 por defecto

        //construimos la consulta en función de pvp limite y antiguedad limite
        //si $antiguedad_limite y $pvp_limite son igual a 0 no se pone AND
        $antiguedad_pvp = '';
        if (($antiguedad_limite > 0) && ($pvp_limite > 0)) {
            $antiguedad_pvp = 'AND ( 
                ((DATEDIFF(NOW() ,pro.date_add) > '.$antiguedad_limite.') AND ((pro.price * 1.21) >= '.$pvp_limite.'))
                OR
                (DATEDIFF(NOW() ,pro.date_add) < '.$antiguedad_limite.')
            )';
        } elseif (($antiguedad_limite == 0) && ($pvp_limite > 0)) {
            $antiguedad_pvp = 'AND pro.price * 1.21 >= '.$pvp_limite.' ';
        } elseif (($antiguedad_limite > 0) && ($pvp_limite == 0)) {
            $antiguedad_pvp = 'AND DATEDIFF(NOW() ,pro.date_add) < '.$antiguedad_limite.' ';
        }
        
        //17/08/2020 Temporalmente hacemos que este proceso ignore a los productos de Cerdá Kids, ya que por sus características los vamos a procesar aparte, por eso quitamos los que tengan id_manufacturer = 76

        //sacamos los productos:
        $sql_productos_para_permitir = 'SELECT pro.id_product AS id_product, pro.reference AS referencia_presta, pro.ean13 AS ean,
            psu.id_supplier AS id_supplier,  
            CASE
            WHEN pro.id_category_default = 89 THEN 1
            ELSE 0
            END
            AS descatalogado,
            CASE
            WHEN pro.id_product IN (SELECT id_product FROM frik_aux_category_product WHERE cat_default = 1)  THEN 1
            ELSE 0
            END
            AS recatalogable,
            psu.product_supplier_reference AS referencia_proveedor, REPLACE(ica.precio, ",","." ) AS coste_proveedor,     
            CASE
            WHEN (ABS(REPLACE(ica.precio, ",","." ) - ROUND(pro.wholesale_price,2))) > 0.1 THEN 1
            ELSE 0
            END
            AS cambiar_coste, pro.weight AS peso
        FROM lafrips_product pro 
            JOIN lafrips_stock_available ava ON pro.id_product = ava.id_product 
            JOIN lafrips_product_supplier psu ON psu.id_product = pro.id_product
            JOIN frik_import_catalogos ica ON ica.referencia_proveedor = psu.product_supplier_reference 
        WHERE ava.out_of_stock IN (0,2) 
        AND ava.id_product_attribute = 0 
        AND ava.quantity <= 0 
        AND psu.id_supplier IN ('.$lista_proveedores.')
        AND psu.id_supplier = ica.id_proveedor
        AND ica.disponibilidad = 1
        AND (ica.eliminado != 1 OR ica.eliminado IS NULL)
        AND psu.product_supplier_reference != ""
        AND pro.id_product NOT IN (14573)        
        AND pro.id_product NOT IN (SELECT id_product FROM lafrips_category_product WHERE id_category IN (2440, 319))
        AND pro.is_virtual = 0
         '.$antiguedad_pvp.'
        AND pro.active = 1
        AND pro.id_manufacturer != 76
        AND pro.cache_is_pack = 0
        AND pro.id_product NOT IN (SELECT id_product FROM lafrips_product_attribute) 
        AND pro.id_product NOT IN (SELECT DISTINCT id_product FROM lafrips_specific_price
            WHERE id_specific_price_rule = 0
            AND id_customer = 0
            AND ((lafrips_specific_price.to = "0000-00-00 00:00:00") OR (lafrips_specific_price.to > NOW()))
            AND reduction_type = "percentage" AND reduction > 0.2
            AND id_product = pro.id_product)
        ORDER BY pro.id_product ASC;';

        $productos = Db::getInstance()->ExecuteS($sql_productos_para_permitir);

        //comprobamos que no haya duplicados, un producto con varios proveedores disponibles al mismo tiempo. Si lo hay se asigna el proveedor por defecto más barato
        $lista_ids = array();
        $contador = 0;
        foreach ($productos as $producto) {
            //metemos los ids en lista_ids y buscamos si el id_product ya existe en el array, si lo encuentra comparamos coste de proveedor y eliminamos el más caro de $productos
            
            if ($key = array_search($producto['id_product'], $lista_ids)){                
                //comparamos coste de ambos productos
                if ($productos[$key]['coste_proveedor'] > $productos[$contador]['coste_proveedor']){
                    //si el coste del primer producto en el array es mayor que el del segundo, eliminamos el primero de $productos y $lista_ids y metemos en $lista_ids el segundo
                    unset($productos[$key]);            
                    unset($lista_ids[$key]);
                    $lista_ids[$contador] = $producto['id_product'];   
                    
                } elseif ($productos[$key]['coste_proveedor'] <= $productos[$contador]['coste_proveedor']){
                    //si el coste del nuevo producto es mayor o igual que el del que ya estaba en lista_ids, se elimina el nuevo de $productos
                    unset($productos[$contador]);                              
                }


            } else {
                $lista_ids[$contador] = $producto['id_product'];   
            }
            
            $contador++;

        }
        $numeroproductospermitir = 0;
        //finalmente trabajamos sobre los productos
        foreach ($productos as $producto) {            
            //instanciamos producto para meter proveedor por defecto, peso si es necesario y mensaje de prepedido, sacar categorías, etc
            $producto_permitido = new Product($producto['id_product']);

            $recatalogar = '';
            //comprobamos si está descatalogado y si se puede recatalogar
            if ($producto['descatalogado'] && $producto['recatalogable']){
                //comprobamos que tenga las categorías disponible recatalogar 2184 y que no tenga ya recatalogar 2179
                $categorias = Product::getProductCategories($producto['id_product']);
                if (in_array(2184, $categorias) && !in_array(2179, $categorias)){
                    //marcamos recatalogar
                    $producto_permitido->addToCategories([2179]);            
                    $recatalogar = 'Si';
                    //introducir el id_product, el proceso (recatalogar_tarea_permitir) y la fecha en la tabla log frik_log_descatalogar
                    //22/12/2020 metemos también el id_employee desde el context o 44 de automatizador si no lo hay
                    $id_employee_automat = Context::getContext()->employee->id;
                    if (!$id_employee_automat) {
                        $id_employee_automat = 44;
                    }
                    
                    Db::getInstance()->Execute("INSERT INTO frik_log_descatalogar (id_product, proceso, id_employee, fecha) VALUES ('".$producto['id_product']."', 'marcado_recatalogar_proceso_permitir',".$id_employee_automat.", NOW());");
                    //Db::getInstance()->Execute("INSERT INTO frik_log_descatalogar (id_product, proceso, fecha) VALUES ('".$producto['id_product']."', 'marcado_recatalogar_proceso_permitir', NOW());"); 
                    
                }
            }
            
            //si ejecutamos manualmente, aquí ponemos que muestre mensaje de Recatalogar a mano, si está descatalogado y no está en la tabla frik_aux_category_product
            if ($producto['descatalogado'] == 1 && $producto['recatalogable'] == 0){
                $recatalogar = 'RECATALOGAR A MANO';
            } elseif ($producto['descatalogado'] == 0){
                $recatalogar = 'No';
            }

            $cambiarcoste = 'No';
            if ($producto['cambiar_coste']){
                $cambiarcoste = 'Si';  //se cambia el coste si o si, pero lo indicamos solo si según la consulta había una diferencia de > 0.1 
            }
            
            //11/11/2021 Para evitar rectalogar productos con coste bajo por haber estado en cajas, a partir de ahora metemos el nuevo coste también en wholesale_price. Antes de pisar el coste existente con el nuevo, comprobamos el coste anterior, almacenamos el cambio en una tabla, y si el porcentaje de diferencia es 20% o más se avisa a Alberto (en un proceso posterior) para que modifique el pvp a su gusto. El porcentaje sería ((coste_final - coste_inicial)/coste_inicial)*100
            $coste_inicial = $producto_permitido->wholesale_price;
            $coste_final = $producto['coste_proveedor'];
            $variacion = (($coste_final - $coste_inicial)/$coste_inicial)*100;
            //actualizamos coste
            $producto_permitido->wholesale_price = $coste_final;
            //guardamos en tabla log              
            Db::getInstance()->Execute("INSERT INTO frik_log_cambio_coste_permitir_pedido
            (id_product, product_reference, product_supplier_reference, old_wholesale_price, new_wholesale_price, porcentaje_variacion, id_supplier, supplier_name, date_add)
            VALUES
            (".$producto['id_product'].",'".$producto_permitido->reference."','".$producto['referencia_proveedor']."',".$coste_inicial.",".$coste_final.",".$variacion.",".$producto['id_supplier'].",'".Supplier::getNameById($producto['id_supplier'])."', NOW())");
            
            //instanciamos el product supplier, sacando primero el id_product_supplier (0 es el id_product_attribute)
            $id_product_supplier = ProductSupplier::getIdByProductAndSupplier($producto['id_product'], 0, $producto['id_supplier']);
            $product_supplier = new ProductSupplier($id_product_supplier);  
            //asignamos nuevo precio      
            $product_supplier->product_supplier_price_te = $producto['coste_proveedor'];
            $product_supplier->save();                    

            $comprobarpeso = 'No';
            //comprobamos que el peso no sea 0, si lo es le ponemos 1.111
            if ($producto['peso'] == 0){        
                $producto_permitido->weight = 1.111;
                $comprobarpeso = 'SI';
            }
            
            //si el proveedor por defecto no es el que vamos a asignar, se lo ponemos
            if ($producto_permitido->id_supplier !== $producto['id_supplier']){            
                $producto_permitido->id_supplier = $producto['id_supplier'];
            }

            //mensaje prepedido. Si es de Cerdá Kids ponemos otro
            //id de manufacturer by name
            $id_manufacturer_cerda_kids = (int)Manufacturer::getIdByName('Cerdá Kids');
            if ($producto_permitido->id_manufacturer == $id_manufacturer_cerda_kids) {
                $available_later = 'Atención: el plazo de envío de este artículo es de tres a seis días.';
            } else {
                $available_later = 'Atención: el plazo de envío de este artículo es de una semana a diez días.';
            }

            //10/01/2022 Si el proveedor que ponemos es Difuzed, ponemos otro mensaje
            if ($producto['id_supplier'] == 7) {
                $available_later = 'Atención: el plazo de envío de este artículo es de hasta tres semanas.';
            } else {
                $available_later = 'Atención: el plazo de envío de este artículo es de una semana a diez días.';
            }

            //generamos el array de idiomas para available later
            $idiomas = Language::getLanguages();

            $available_later_todos = array();
            foreach ($idiomas as $idioma) {                    
                $available_later_todos[$idioma['id_lang']] = $available_later;                    
            }

            $producto_permitido->available_later = $available_later_todos; 
            
            // $producto_permitido->available_later = [1 => $available_later, 11 => $available_later, 12 => $available_later, 13 => $available_later, 14 => $available_later];  

            $producto_permitido->save();

            //Marcamos permitir pedidos (0 no permitir, 1 permitir, 2 por defecto)
            StockAvailable::setProductOutOfStock($producto['id_product'], 1);
            $numeroproductospermitir++;

            //Añadimos el log de lo que hemos hecho a frik_log_import_catalogos
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname;
            $id_product = $producto['id_product'];
            $referencia_producto = $producto['referencia_presta'];
            $ean = $producto['ean'];
            $referencia_proveedor = $producto['referencia_proveedor'];
            $id_proveedor = $producto['id_supplier'];
            $nombre_proveedor = Supplier::getNameById($id_proveedor);
            Db::getInstance()->Execute("INSERT INTO frik_log_import_catalogos
                 (operacion, id_product, referencia_presta, ean, referencia_proveedor, id_proveedor, nombre_proveedor, user_id, user_nombre, date_add) 
                 VALUES ('Permitir Pedido Proceso Global',".$id_product.",'".$referencia_producto."','".$ean."','".$referencia_proveedor."',".$id_proveedor.",
                        '".$nombre_proveedor."',".$id_empleado.",'".$nombre_empleado."',  NOW());"); 


            $tabla .= '<tr><td>'.$id_product.'</td><td>'.$referencia_producto.'</td><td>'.$ean.'</td><td>'.$referencia_proveedor.'</td><td>'.$nombre_proveedor.'</td><td>'.$recatalogar.'</td><td>'.$cambiarcoste.'</td><td>'.$comprobarpeso.'</td></tr>'; 
        }
        if ($numeroproductospermitir == 0){
            $numero = 'Ningún producto';
        } else {
            $numero = $numeroproductospermitir.' producto/s';
        }
        $tabla .= '</tbody><caption>Añadido Permitir Pedido - '.$numero.'</caption></table><br><br>';
        $tabla .= '</table>';

        
        $output = '<div class="panel">'.$tabla.'</div>';
        
        return $output;

    }    

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {            
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /*
        05/06/2020 Esta función nos permitirá seleccionar nuevos proveedores para el proceso de permitir pedidos automático. De este modo podremos tener catálogos guardados en frik_import_catalogos  cuyos productos podrán ponerse o no de forma automática en permitir pedido según su stock, pero también podremos tener catálogos subidos, que si no se han seleccionado aquí, estarán disponibles para crear el producto con el importador, pero no para el automatizador.        
    */
    protected function postProcessAsignarProveedores() 
    {
        // $output = '<div class="panel">HOLA</div>';
        
        // return $output;

        //sacamos todos los proveedores activos en Prestashop
        // $sql_proveedores_presta = 'SELECT id_supplier, name FROM lafrips_supplier WHERE active = 1 ORDER BY name ASC';
        // $proveedores_presta= Db::getInstance()->ExecuteS($sql_proveedores_presta);

        //Sacar los proveedores disponibles en la tabla frik_import_catalogos para mostrarlos
        $sql_proveedores_tabla = 'SELECT DISTINCT id_proveedor, nombre_proveedor 
        FROM frik_import_catalogos';
        $proveedores_tabla = Db::getInstance()->ExecuteS($sql_proveedores_tabla);

        if (!$proveedores_tabla || empty($proveedores_tabla))
            $this->displayWarning('No se encontraron proveedores en la tabla');        

        //sacamos de lafrips_configuration los proveedores configurados para permitir pedido
        //$proveedores_permitir_json =  Db::getInstance()->executeS("SELECT value FROM lafrips_configuration where name = 'IMPORTA_PROVEEDOR_PROVEEDORES'")[0]['value'];
        $proveedores_permitir_json = Configuration::get('IMPORTA_PROVEEDOR_PROVEEDORES');
        $proveedores_permitir_array = json_decode($proveedores_permitir_json);

        //creamos un array que contiene id_supplier, nombre y si está automatizado o no (1,0)
        $proveedores = array();
        $contador = 0;
        foreach ($proveedores_tabla as $proveedor_tabla){
            $proveedores[$contador]['id_proveedor'] = $proveedor_tabla['id_proveedor'];
            $proveedores[$contador]['nombre_proveedor'] = $proveedor_tabla['nombre_proveedor'];
            //si el id_proveedor está en el array de proveedores de la tabla configuration, es que tiene automatización activa
            if (in_array( $proveedor_tabla['id_proveedor'], $proveedores_permitir_array )){
                $proveedores[$contador]['automatizado'] = 1; 
            }else{
                $proveedores[$contador]['automatizado'] = 0;
            }
            $contador++;
        }        

        $this->context->smarty->assign(array('proveedores' => $proveedores));

        return $this->display(__FILE__,'views/templates/admin/proveedores.tpl');
        
    }
    
    /*
        08/06/2020 Esta función nos permitirá guardar nuevos proveedores para el proceso de permitir pedidos automático. De este modo podremos tener catálogos guardados en frik_import_catalogos  cuyos productos podrán ponerse o no de forma automática en permitir pedido según su stock, pero también podremos tener catálogos subidos, que si no se han seleccionado aquí, estarán disponibles para crear el producto con el importador, pero no para el automatizador.
    */
    protected function postProcessProveedoresAutomatizados()
    {
        //recibimos por post los checkbox marcados en la página para seleccionar proveedores a automatizar.
        $proveedores_automatizados = Tools::GetValue('proveedores_automatizados');
        //pasamos el array a json y lo insertamos con update en la tabla lafrips_configuration
        // if (Db::getInstance()->Execute("UPDATE lafrips_configuration SET value = '".json_encode($proveedores_automatizados)."', date_upd = NOW() WHERE name = 'IMPORTA_PROVEEDOR_PROVEEDORES'")){
        if (Configuration::updateValue('IMPORTA_PROVEEDOR_PROVEEDORES', json_encode($proveedores_automatizados))){
            return $this->displayWarning('Configuración de proveedores guardada').$this->renderForm();            
        }else{
            return $this->displayWarning('Hubo un error al guardar la configuración').$this->renderForm();
        }   
    }

}
