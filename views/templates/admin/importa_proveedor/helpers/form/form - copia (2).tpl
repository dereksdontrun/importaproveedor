{*
 *
 * Importador de productos desde catálogo de proveedor
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 *}
{extends file="helpers/form/form.tpl"}
{block name="other_fieldsets"}
    <input type="hidden" id="product_ids" name="product_ids" value="" />
    <input type="hidden" id="product_ids_to_delete" name="product_ids_to_delete" value="0" />
    <input type="hidden" name="supply_order" value="1" />
    <!-- Cargamos el script jquery-ui que nos permite mostrar una ventana de diálogo para recoger datos -->
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    

    <div class="panel">
        <table id="products_in_search" class="table">
            <caption style="text-align:left"><h2><span id='num_prods'></span></h2></caption>
            <thead>
                <tr class="nodrag nodrop">                    
                    <th class="fixed-width-xs"><span class="title">Proveedor</span></th>
                    <th class="fixed-width-sm text-center"><span class="title">Referencia Proveedor</span></th>
                    <th class="fixed-width-sm text-center"><span class="title">EAN</span></th>
                    <th class="text-center"><span class="title_box">Nombre</span></th> 
                    <th class="text-center"><span class="title_box">Descripción</span></th>
                    <th class="text-center"><span class="title_box">Web</span></th>
                    <th class="text-center"><span class="title_box">Precio</span></th>
                    <th class="text-center"><span class="title">Imagen</span></th>
                    <th class="fixed-width-xs text-center"><span class="title">Disponibilidad</span></th>
                    <th class="fixed-width-xs text-center"><span class="title">Atributo</span></th>
                    <th class="fixed-width-xs text-center"><span class="title">Prestashop EAN</span></th>
                    <th class="fixed-width-xs text-center"><span class="title">Prestashop Referencia</span></th>
                    <th class="fixed-width-xs text-center"><span class="title">Otros Proveedores - Precio</span></th>
                    <th class="fixed-width-sm">&nbsp;</th>                    
                </tr>
            </thead>
            <tbody>
                <!-- div con display none oculto desde back.css que da forma a la ventana de diálogo para introducir la referencia para un nuevo producto si se utiliza el botón de Crear Producto -->
                <!-- 28/10/2021 Voy a añadir un check que al pulsarse muestra un input donde introducir la referencia de un producto del que queremos copiar las categorías al crear el producto nuevo. Recogeremso categorías, nombre, descripción, seo y tipo de producto. En este cuadro de dialog solo mostraremos las categorías y el nombre del producto por si lo quieren usar para crear el nuevo. La descripción del producto y su nombre se "pegarán" al final de la descripción que viene en el importador para el producto. El tipo y el seo se pondrán al producto.  La descripción, tipo, seo etc no es necesaria aquí, la sacaremos en el controlador al crear el prodcuto. -->
                <div id="dialog" title="Creando Producto">
                    <h2>Hola {Context::getContext()->employee->firstname}, introduce la referencia para el producto que vas a crear. <br><br>Recuerda que no debe estar repetida.</h2>
                    <br>
                    <h2><span id='error_formato_ref' style="display:none; color:red;">La referencia debe tener formato AAA12345678</span></h2>
                    <h2><span id='error_ref_repetida' style="display:none; color:red;">¡¡Esa referencia me suena!!</span></h2>
                    <form action="#" method="post">                    
                        <p><h2><span style="font-weight:bold;" title="Copiará las categorías, tipo de producto y SEO y añadirá su descripción a la que se importa por defecto">Importar características: </span><input type="checkbox" id="check_importar" name="check_importar" /><h2>                          
                        <input type="text" name="producto_importar" id="producto_importar" style="display: none;" placeholder="Introduce la referencia completa del producto">   
                        </p>
                        <span id="categorias_clonar" style="display:none; font-size:small;"></span>
                        <p><h2><span style="font-weight:bold;">Referencia:</span><h2>
                            <input type="text" id="referencia_nuevo" />
                        </p>  
                        <p><h2><span style="font-weight:bold;">Nombre:</span><h2>
                            <input type="text" id="dialog_nombre_producto" />
                        </p>                        
                        <input type="submit" id="dialog_submit_referencia" value="Crear">                        
                    </form>
                </div>                

                 <!-- 14/07/2020 otro div con display none oculto desde back.css que da forma a la ventana de diálogo donde crearemos un select con los grupos de atributos para elegir al crear productos con atributos, y que en función del grupo escogido y el número de atributos a crear para el producto, generaremos dinámicamente tantos inputs para referencia y selects para atributos como atributos a crear-->
                <div id="dialog_atributos" title="Creando Producto con Atributos">
                    <h2>Hola {Context::getContext()->employee->firstname}</h2>
                    {* <h2 id="texto_3_atributos"><input type="checkbox" id="doble_combinacion" name="doble_combinacion" value="" /> Marca si el producto tiene combinaciones dobles</h2> *}
                    <h2 id="texto_1_atributos">Selecciona el grupo de atributos al que corresponden las combinaciones del producto que estás creando.</h2>
                    <h2 id="texto_2_atributos">Ahora, selecciona para cada combinación el atributo que le corresponde e introduce su referencia de combinación.</h2>
                    <br>
                    {* <h2><span id='error_formato_ref_atributos' style="display:none; color:red;">La referencia debe tener formato AAA12345678</span></h2>
                    <h2><span id='error_ref_repetida_atributos' style="display:none; color:red;">¡¡Esa referencia me suena!!</span></h2> *}
                    <form action="#" method="post">
                        {* <p><h2><span style="font-weight:bold;">Grupo:</span><h2> *}
                        <p>
                            <span id="span_grupo_atributos"></span>
                        </p> 
                        <p>
                            <span id="span_combinaciones"></span>
                        </p> 
                        
                        <input type="submit" id="dialog_submit_combinaciones" value="Crear">
                    </form>
                </div>

            </tbody>
        </table>

    </div>

    <script type="text/javascript">
        product_infos = null;
        debug = null;
        if ($('#product_ids').val() == '')
            product_ids = [];
        else
            product_ids = $('#product_ids').val().split('|');

        //A la función addProduct se la llama desde back.js con la variable line, que corresponde una a cada producto de data.products
        function addProduct(product_infos)
        {
            //si el producto venía con imagen en catálogo creamos el enlace, si no mostramos el logo de la frikileria
            var img = '';

            if(!product_infos.url_imagen || product_infos.url_imagen==''){
                img = '<img itemprop="image" src = "https://lafrikileria.com/img/logo_producto_medium_default.jpg"/>';
            }else{
                //para evitar que se muestre un link roto en los casos (abysse) en los que la imagen no esté disponible en el sevidor del proveedor utilizamos onerror en el elemento img mostrando la imagen por defecto
                img = '<img itemprop="image" src="'+product_infos.url_imagen+'" onerror="this.onerror=null;this.src=\'https://lafrikileria.com/img/logo_producto_medium_default.jpg\';" style="width:120px;"/>';
            }


            //formateamos precio de proveedor a 2 decimales (primero pasamos la cadena a float)
            var precio = parseFloat(product_infos.precio,10).toFixed(2);
            
            //a partie de SEPTIEMBRE 2020 la carga se hace mediante PHP y nomysql desde la carpeta sincronizada
            //si el producto es de redstring (id_proveedor = 24) la descripción será el enlace al producto en su web (desde 29/06/2020 hay un campo descripción que contiene una columna del catálogo concatenada con la url de producto, que está ahora en un campo independiente url_producto NO- HAY QUE LIMPIAR EL STRING, de momento no se concatena,solo ponemos url). Si es HEO añadimos la referencia de producto a su url,etc. Mostramos dicho enlace. Para Aby y Karactermanía, y Erik - 05/06/2020 - usamos la url de resultados de busqueda
            //02/03/2021 Añado Extended Play id 70
            //03/08/2021 añado Globomatik, de momento no podemos formar url, pongo la misma para todos
            if (product_infos.id_proveedor == 24){
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Redstring</a>';
            }else if(product_infos.id_proveedor == 4){
                //var url_heo = 'https://www.heo.com/de/es/product/'+product_infos.referencia_proveedor;
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en HEO</a>';
            }else if(product_infos.id_proveedor == 53){
                //var url_kar = 'https://karactermania.com/es/catalogsearch/result?query='+product_infos.referencia_proveedor;
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Karactermanía</a>';
            }else if(product_infos.id_proveedor == 14){
                //var url_aby = 'http://trade.abyssecorp.com/e/en/recherche?controller=search&orderby=date_add&orderway=desc&search_query='+product_infos.referencia_proveedor;
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Abysse</a>';
            }else if(product_infos.id_proveedor == 8){
                //var url_eri = 'https://www.grupoerik.com/es/buscar?controller=search&orderby=position&orderway=desc&search_query='+product_infos.referencia_proveedor+'&submit_search=';
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en ERIK</a>';
            }else if(product_infos.id_proveedor == 6){ //SD Distribuciones. Hemos generado con mySql el enlace a la búsqueda en el campo url_producto y descripción            
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en SD Dist.</a>';
            }else if(product_infos.id_proveedor == 7){ //Difuzed - Bioworld. 29/06/2020 Hemos generado con mySql el enlace a la búsqueda en el campo url_producto y descripción            
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Difuzed</a>';
            }else if(product_infos.id_proveedor == 121){ //Distrineo. 29/06/2020 Hemos generado con mySql el enlace a la búsqueda en el campo url_producto y descripción. También imagen, un lío de concatenaciones            
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Distrineo</a>';
            }else if(product_infos.id_proveedor == 111){ //Noble. 29/06/2020 Hemos generado con mySql el enlace a la búsqueda en el campo url_producto y descripción. Muchos ean mal, contienen referencias            
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Noble</a>';
            }else if(product_infos.id_proveedor == 65){ //Cerdá. 20/07/2020 Hemos generado con mySql el enlace al producto en el campo url_producto y descripción.           
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Cerdá</a>';
            }else if(product_infos.id_proveedor == 5){ //Ociostock. 18/09/2020 
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Ociostock</a>';
            }else if(product_infos.id_proveedor == 70){ //Extended Play. 02/03/2021
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Extended Play</a>';
            }else if(product_infos.id_proveedor == 137){ //Superplay. 10/03/2021
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Ver en Superplay</a>';
            }else if(product_infos.id_proveedor == 156){ //Globomatik. 03/08/2021
                var enlace_web = '<a href="'+product_infos.url_producto+'"  style="text-decoration:none; color:red;" target="_blank">Web Globomatik</a>';
            }

            //Si no tiene descripción ponemos un mensaje de No proporcionada por proveedor.
            var descripcion = product_infos.descripcion;
            if ((product_infos.descripcion == '')||(!product_infos.descripcion)){
                descripcion = 'Descripción no proporcionada por proveedor.';
            }
            //Abysse no proporciona descripción en su catálogo excel
            /*if (product_infos.id_proveedor == 24){
                descripcion = 'Descripción no proporcionada por proveedor.';
            }*/

            //La disponibilidad según sus catálogos (0, 1 o 2)
            //Si el producto es HEO y hay fecha en fecha_llegada, la ponemos junto a Prepedido
            var disponibilidad = '';
            if (product_infos.disponibilidad == 0){
                disponibilidad = 'No disponible';
            }else if (product_infos.disponibilidad == 1){
                disponibilidad = 'En Stock';
            }else if (product_infos.disponibilidad == 2){
                disponibilidad = 'Prepedido';
                //si es HEO
                if (product_infos.id_proveedor == 4){
                    if ((product_infos.fecha_llegada)&&(product_infos.fecha_llegada !== '0000-00-00')){                        
                        disponibilidad = 'Prepedido<br>'+product_infos.fecha_llegada;                        
                    } 
                }            
            } 

            //Si es un producto con atributos (no es seguro), el campo de redsting talla y de abysse size envían algo. Con heo y karactermania no tenemos info
            var atributo = '';
            if (product_infos.atributo){
                atributo = product_infos.atributo;
            }else {
                atributo = '-';
            }

            //Si el producto existe en Prestashop, ya sea el ean o ean y referencia de proveedor, mostraremos el id_product con enlace a la ficha de edición de producto en Prestashop, si son varios, mostraremos un enlace por producto. Lo separamos por coincidencia de ean o coincidencia de referencia de proveedor.
            var prestashop_referencia = '';
            var prestashop_ean = '';
            var url_producto_prestashop = '';

            //Referencia de proveedor repetida
            if (product_infos.prestashop_ref_prov == null){
                prestashop_referencia = 'No';
            }else if (product_infos.prestashop_ref_prov != null){                
                //El token para la url lo sacamos utilizando smarty para introducir la llamada a la herramienta Tools::getAdminTokenLite que nos va a dar el de, en este caso, la administración de productos, que es a donde apunta la url con el controlador
                var token_adminProducts = '{Tools::getAdminTokenLite('AdminProducts')}';

                //product_infos.prestashop_ref_prov puede contener un id_product o varios separados por |. Hacemos split y generamos un enlace para cada id_product.
                var ids_repes_ref = product_infos.prestashop_ref_prov.split('|');
                ids_repes_ref.forEach(function creaEnlace(item,index){
                    url_producto_prestashop = 'https://lafrikileria.com/lfadminia/index.php?controller=AdminProducts&id_product='+item+'&updateproduct&token='+token_adminProducts;
                    prestashop_referencia += '<a href="'+url_producto_prestashop+'"  style="text-decoration:none; color:red;" target="_blank">'+item+'  </a>';   
                });                           
            }

            //EAN repetido
            if (product_infos.prestashop_ean == null){
                prestashop_ean = 'No';
            }else if (product_infos.prestashop_ean != null){                
                //El token para la url lo sacamos utilizando smarty para introducir la llamada a la herramienta Tools::getAdminTokenLite que nos va a dar el de, en este caso, la administración de productos, que es a donde apunta la url con el controlador
                var token_adminProducts = '{Tools::getAdminTokenLite('AdminProducts')}';

                //product_infos.prestashop_ean puede contener un id_product o varios separados por |. Hacemos split y generamos un enlace para cada id_product.
                var ids_repes_ean = product_infos.prestashop_ean.split('|');
                ids_repes_ean.forEach(function creaEnlace(item,index){
                    url_producto_prestashop = 'https://lafrikileria.com/lfadminia/index.php?controller=AdminProducts&id_product='+item+'&updateproduct&token='+token_adminProducts;
                    prestashop_ean += '<a href="'+url_producto_prestashop+'"  style="text-decoration:none; color:red;" target="_blank">'+item+'  </a>';   
                });                           
            }

            //Si dentro de la tabla de catálogos existe otro producto con el mismo ean pero diferente proveedor, indicamos ese/esos otros proveedores
            if (product_infos.otro_proveedor == null){
                otro_proveedor = 'No encontrado';
            }else if (product_infos.otro_proveedor != null){
                otro_proveedor = product_infos.otro_proveedor;
            }
            
            //CREAR BOTÓN
            //Dependiendo del resultado de la busqueda se mostrará un botón u otro. Si el producto no existe en prestashop (no se encuentra ni ean ni referencia de proveedor) el botón creará el producto de cero con ese proveedor. Si la referencia de proveedor existe en prestashop, el botón no será funcional, ya que se duplicaría el producto. Si se encuentra el ean pero no la referencia sería porque el producto existe con otros proveedores, el botón debería añadir solo el proveedor al producto existente. Hay que mostrar un mensaje de aviso para que el usuario compruebe que se corresponden los productos. En este caso, si el ean encontrado solo coincide con producto y en la tabla products, se permite agregar, pero si coincide con varios productos, o se encuentra en la tabla product_attribute, no permitimos agregar.
            //Además, añadimos un check para marcar el producto como atributo si el producto no existe de antes
            var boton = '';            
            var checks_atributos = '';            
            if ((product_infos.prestashop_ean == null)&&(product_infos.prestashop_ref_prov == null)){                
                boton = '<button type="button" id="crear|'+product_infos.id_import_catalogos+'" class="btn btn-default crear"><i class="icon-save"></i> Crear Producto </button>';               
                
                //si el producto es candidato a tener atributos (a ser un atributo) se ponen los checks 
                checks_atributos = '<br><br><input type="checkbox" id="checkbox_atributo|'+product_infos.id_import_catalogos+'" name="checkbox_atributo_'+product_infos.id_import_catalogos+'" value="checkbox_'+product_infos.id_import_catalogos+'" />';

            }else if (product_infos.prestashop_ref_prov !== null){
                //añadimos un input hidden para almacenar este html del botón para cuando se marca o se quita el check de crear atribtuos, poder volver a ponerlo, sacandolo del value de dicho input hidden. De momento no creamos atributos para productos que ya existen!!
                boton = '<span class="title"><i class="icon-question-sign"></i> El producto ya existe</span>';
                //hidden_atributo = '<input id="escondido_check_atributo_'+product_infos.id_import_catalogos+'" type="hidden" name="" value="'.boton.'"/>';                

            }else if ((product_infos.prestashop_ean !== null)&&(product_infos.prestashop_ref_prov == null)){
                //el ean se ha encontrado en Prestashop, si solo está una vez como ean de producto permitimos agregar, si está más de una vez o/y en un atributo, no permitimos agregar. Es decir, si el ean aparece más de una vez, o en atributo, no se agrega sin comprobar antes.
                if ((product_infos.ean_producto_unico == 0)&&(product_infos.ean_atributo == 0)){
                    boton = '<button type="button" id="agregar|'+product_infos.id_import_catalogos+'" class="btn btn-default agregar"><i class="icon-plus"></i> Añadir Proveedor </button>';
                }else{
                    boton = '<span class="title ean_repetido"><i class="icon-question-sign"></i> EAN repetido</span>';
                }
                
            }


            //LÍNEA DE PRODUCTO
            // añade nueva línea al final de la tabla (id products_in_search)
            $('#products_in_search > tbody:last').append(
                '<tr>'+
                '<td><div class="fixed-width-xs">'+product_infos.nombre_proveedor+'</div></td>'+
                '<td class="text-center">'+product_infos.referencia_proveedor+'</td>'+
                '<td class="text-center"><div class="fixed-width-md">'+product_infos.ean+'</div></td>'+
                '<td id="nombre_linea_'+product_infos.id_import_catalogos+'">'+product_infos.nombre+'</td>'+ // Ponemos id nombre_linea+id para acceder al nombre en back.js  
                '<td><div class="scrolltabla">'+descripcion+'</div></td>'+
                '<td class="text-center">'+enlace_web+'</td>'+
                '<td class="text-center">'+precio+'</td>'+
                '<td class="text-center">'+img+'</td>'+
                '<td class="text-center">'+disponibilidad+'</td>'+
                '<td class="text-center">'+atributo+checks_atributos+'</td>'+
                '<td class="text-center">'+prestashop_ean+'</td>'+
                '<td class="text-center">'+prestashop_referencia+'</td>'+
                '<td class="text-center">'+otro_proveedor+'</td>'+
                //'<td><button type="button" id="update|'+product_infos.id_import_catalogos+'" class="btn btn-default update"><i class="icon-save"></i> Crear Producto'+'</button></td>'+
                '<td><span id="botoncito_'+product_infos.id_import_catalogos+'">'+boton+'</span></td>'+
                '</tr>'
            );

            // add the current product id to the product_id array - used for not show another time the product in the list
            product_ids.push(product_infos.id);

            // update the product_ids hidden field
            $('#product_ids').val(product_ids.join('|'));

            //contar el número de productos (ids) en el array para mostrar en la lista, saldrá en pantalla la última cantidad, se vacia desde back.js
            if(product_ids.length > 1){
                $('#num_prods').html('<span>'+ product_ids.length + ' productos</span><span style="font-size:small;"> (100 max)</span>');
            }else{
                $('#num_prods').text(product_ids.length + ' producto');
            }

            // clear the product_infos var
            product_infos = null;
        }

    </script>
{/block}
