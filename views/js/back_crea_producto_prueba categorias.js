/**
 * Creador de productos Flash
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

 //escondemos el select para asignar cuantas combinaciones tendrá el producto y el de grupos de atributos hasta que se marque el checkbox de combinaciones
$(document).ready(function(){
    $('#num_combination').hide();
    $('#attribute_group').hide(); 
    $('#weight').val(1.111); 
    $('#meta_title').val('Producto por 34,90€ – LaFrikileria.com');
    $('#meta_description').val('Compra todos los artículos de - en nuestra tienda de regalos originales de Cine, Series de Tv, Videojuegos, Superhéroes, Manga y Anime');    
    //escondemos los inputs para url de imágenes y les ponemos placeholder
    $('.imagenes').hide();      
    $('.imagenes').attr("placeholder", "Otras imágenes - Introduce una url válida");
    $('#cover_image_1').attr("placeholder", "Imagen Principal - Introduce una url válida para la imagen por defecto del producto");

});

$(function(){
    //si se marca algún check del formulario:

    //$('#configuration_form').on('change', 'input:checkbox[id="producto_combinaciones_si"]', function(e){ 
    //$('#producto_combinaciones_si').change(function() { 
    $('#configuration_form').on('change', 'input[type="checkbox"]', function(e){    
        //si se marca el de combinaciones        
        if (this.id == 'producto_combinaciones_si') {  
            //si está checado mostramos primero el select de grupo de atributos, y escondemos el input de referencia de proveedor, ya que habrá una por combinación
            if($(this).is(':checked')) {    
                $('#attribute_group').show();
                $('#product_supplier_reference').prop('disabled', true);
                //$('#product_supplier_reference').val('');    
                $('#product_supplier_reference').attr("placeholder", "Debes introducir una referencia por cada combinación");   
                $('.combinaciones').show(); //si hay combinaciones pero se habían ocultado al desmarcar check, se muestran
                
            }else{
                //si el check se quita, escondemos los select de atributos y número de combinaciones y escondemos las creadas, así, si se vuelve a marcar, siguen ahí mientras no se cambie de grupo de atributos
                $('#attribute_group').hide();
                //$('#attribute_group').val(0);
                $('#num_combination').hide();
                //$('#num_combination').val(0);
                //$('.combinaciones').remove();
                $('.combinaciones').hide();
                $('#product_supplier_reference').prop('disabled', false);
                $('#product_supplier_reference').attr("placeholder", "");
                
            }
        }

        //si se marca el de imagenes
        if (this.id == 'producto_imagenes_si') {         
            //si está checado mostramos los inputs de imágenes
            if($(this).is(':checked')) {    
                $('.imagenes').show();           
                
            }else{
                //si el check se quita, escondemos los inputs de imágenes, si se vuelve a marcar, siguen ahí
                $('.imagenes').hide();
                
            }
        }

        //si se marca el de categorías. Debe mostrar el árbol de categorías para lo cual ha de hacer llamada ajax         
        if (this.id == 'producto_categorias_si') { 
            
            if($(this).is(':checked')) { 
                
                var dataObj = {};
                $.ajax({
                    url: 'index.php?controller=AdminCrearProductos' + '&token=' + token + "&action=get_category_tree" + '&ajax=1' + '&rand=' + new Date().getTime(),
                    type: 'POST',
                    data: dataObj,
                    cache: false,
                    dataType: 'json',
                    success: function (data, textStatus, jqXHR)
                    
                    {
                        if (typeof data.error === 'undefined')
                        {
                            console.log('llamada a getcategorytree vuelve ok');
                            console.log(data.arbol);
                            var arbolito = '';
                            arbolito += '<div class="panel">'
                            // Object.entries(data.arbol).forEach(([key, value]) => {

                            //     arbolito +=  '<h2>'+value.name+'</h2>';

                            //     Object.entries(value.children).forEach(([key, value]) => {
                            //         arbolito +=  '<h3>'+value.name+'</h3>';
                            //     });
                            // });

                            Object.entries(data.arbol).forEach(([key, value]) => {
                                
                                arbolito +=  '<ul id="associated-categories-tree" class="cattree tree full_loaded">	<li class="tree-folder"><span class="tree-folder-name"><input type="checkbox" name="categoryBox[]" value="'+value.id_category+'"><i class="icon-folder-open"></i>      <label class="tree-toggler">'+value.name+'</label></span>';

                                Object.entries(value.children).forEach(([key, value]) => {
                                    arbolito +=  '<ul class="tree" style="display: block;"><li class="tree-folder"><span class="tree-folder-name"><input type="checkbox" name="categoryBox[]" value="'+value.id_category+'"><i class="icon-folder-close"></i><label class="tree-toggler">'+value.name+'</label></span>';
                                });

                                arbolito += '</li></ul>'; 
                            });

                            arbolito +=  '</li></ul>';

                            arbolito += '</div>'

                            $('#producto_categorias_si').closest('div').append(arbolito);

        
                            
                            // {foreach from=$allCategories item=mainCategory}
                                // <div class="categoryBox">
                                //     <h2>{$mainCategory.name}</h2>
                                //     <p>{$mainCategory.description}</p>  
                                // </div>
                                // {foreach from=$mainCategory.children item=subCategory}
                                //     <div class="categoryBox">
                                //     <h3>{$subCategory.name}</h3>
                                //     <p>{$subCategory.description}</p>
                                //     </div>
                                // {/foreach}
                            // {/foreach}
                        }
                        else
                        {
                            
                            showErrorMessage(data.message);
                        }
        
                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        showErrorMessage('ERRORS: ' + textStatus);
                    }
                });  //fin ajax 
            }
        }

    });

    //si se selecciona algo en el select de grupo de atributos, mostramos select para escoger cuantas combinaciones tendrá el producto 
    $('#attribute_group').on('change', function(e){
        $('#num_combination').show();
        //si se vuelve a cambiar el select de atributos, queremos resetear el de numero de combinaciones
        $('#num_combination').val(0);
        //si se vuelve a cambiar el select de atributos, queremos eliminar los divs de combinaciones
        $('.combinaciones').remove();
   
    });
    
    //cuando cambia el select de número de combinaciones, mostramos para generar combinación tantas veces como el número seleccionado
    $('#num_combination').on('change', function(e){
        //cada vez que se selecciona un número eliminamos los divs de clase combinaciones que se hubieran generado, si no se irían sumando
        $('.combinaciones').remove();

        //al seleccionar un grupo de atributos, tenemos que sacar los atributos que les corresponden para mostrarlos luego en otro select, para cada combinación. Necesitamos usar ajax
        var dataObj = {};
        dataObj['id_attribute_group'] = $('#attribute_group').val();
        console.log(dataObj);
        console.log(dataObj['id_attribute_group']);

        //mediante ajax pedimos los atributos que corresponden al grupo, id y nombre
        $.ajax({
            url: 'index.php?controller=AdminCrearProductos' + '&token=' + token + "&action=atributos_grupo" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {
                    console.log('Atributos encontrados');
                    //recibimos via ajax en data.lista_atributos la lista de id_attribute con sus nombres para el grupo de atributos
                    console.log(data.lista_atributos);
                    //var lista_atributos = new Array();
                    //lista_atributos = data.lista_atributos;
                    //console.log(lista_atributos);
                    //showSuccessMessage(data.message);   
                    
                    //sacamos el nº de combinaciones que se van a crear
                    num_combination = $('#num_combination').val(); 
                    $('#num_combination').closest('div').append('<br>');
                    //por cada combinación, añadimos un select con los atributos obtenidos por ajax, y tres inputs para la referencia, el ean y la referencia de proveedor
                    for (i = 1; i <= num_combination; i++) {
                        //generamos el select con los atributos correspondientes al grupo de atributos escogido antes, se ha recibido la info por ajax y almacenado en lista_atributos
                        var select_atributos = '<select id="attribute_'+i+'" class="atributos" name="attribute_'+i+'" style="display: block;"><option value="0">Escoge '+$('#attribute_group option:selected').text()+'</option>'
                        Object.entries(data.lista_atributos).forEach(([key, value]) => {
                            select_atributos +=  '<option value="'+key+'">'+value+'</option>';
                        });
                        
                        select_atributos += '</select>';

                        $('#num_combination').closest('div').append("<div class='col-lg-7 combinaciones'><p><b>"+$('#attribute_group option:selected').text()+" "+i+"</b></p><p>"+select_atributos+"</p><p>Referencia Combinación <input class='referencia_combinacion' id='combination_reference_"+i+"' type='text' name='combination_reference_"+i+"' value='"+$('#product_reference').val()+"-'></p><p>Ean <input class='ean_combinacion' id='combination_ean_"+i+"' type='text' name='combination_ean_"+i+"'  placeholder='Ean combinación'></p><p>Referencia Proveedor <input class='referencia_proveedor_combinacion' id='combination_supplier_reference_"+i+"' type='text' name='combination_supplier_reference_"+i+"'  placeholder='Referencia de proveedor de combinación'></p><input type='hidden' id='hidden_attribute_"+i+"' name='hidden_attribute_"+i+"'></input><br></div>");
                    } 

                    //cuando uno de los select creados dinamicamente para los atributos cambia, eliminar de los otros la opción escogida. Además, si ya se había escogido un atributo, volver a permitir que sea seleccionado, es decir, ponerlos enabled de nuevo
                    //primero sacamos el id de atributo que se va a pulsar con el focus y lo guardamos en atributo_previo. Para no estar activando y desactivando el default(0 - Escoge atributo) si vale 0 le ponemos valor 99999999 de modo que no hará nada
                    $('.atributos').on('focus', function (){
                        if (this.value != 0){
                            atributo_previo = this.value;
                        } else {
                            atributo_previo = 99999999;
                        }                      
                    }).on('change', function(){
                        //ahora almacenamos el atributo que se ha seleccionado en atributo_seleccionado
                        atributo_seleccionado = $(this).val();
                        console.log('previo '+atributo_previo);
                        console.log('seleccionado '+atributo_seleccionado);

                        $('.atributos').each(function() {
                            //por cada select, volvemos a poner disabled false en el atributo_previo, en todos, y lo quitamos en el atributo_seleccionado, en todos menos en el que estamos 
                            $('.atributos').find('option[value="' + atributo_previo + '"]').prop('disabled', false);                          
                            $('.atributos').not(this).find('option[value="' + atributo_seleccionado + '"]').prop('disabled', true);                      
                        });

                        //además, asignamos a su input hidden correspondiente el valor escogido, para procesarlo desde el controlador. Primero sacamos el valor de i, su número de select, para saber qué input hidden corresponde, qué combinación
                        combinacion = this.id.substr(-1, 1);
                        console.log('orden '+combinacion);
                        $('#hidden_attribute_'+combinacion).val(atributo_seleccionado);
                        console.log('valor hidden '+$('#hidden_attribute_'+combinacion).val());

                        //para ahorrar tiempo, al seleccionar un atributo, concatenamos el texto del mismo a la referencia de producto. Como se puede cambiar de atributo varias veces, en realidad no concatenamos sino que creamos de nuevo la referencia añadiendo a la referencia el guión y el atributo cada vez que cambie el select. Con atribtuos largos tendrán que editarlos manualmente o quitar espacios
                        texto_atributo = $('#attribute_'+combinacion+' option:selected').text();
                        $('#combination_reference_'+combinacion).val($('#product_reference').val()+'-'+texto_atributo);
                    });

                    

                }
                else
                {
                    
                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax  
       

    });

    //cuando se introduce una referencia para el producto, al hacer focusout, comprobar que la referencia tenga el formato adecuado y por ajax, usando la función del importador, comprobar que no esté repetida
    $('#product_reference').focusout(function(){
        var referencia_introducida = $("#product_reference").val();            
            
        //comprobamos que la referencia introducida tenga formato AAA11111111
        if (/^[A-Za-z]{3}[0-9]{8}$/.test(referencia_introducida)){
            console.log('La referencia ES correcta');
            var dataObj = {};
            dataObj['referencia'] = referencia_introducida;
            console.log(dataObj);
            console.log(dataObj['referencia']);

            // obtenemos token del input hidden que hemos creado con id 'token_admin_importa_proveedor_'.$token_admin_importa_proveedor, para ello primero buscamos el id de un input cuyo id comienza por token_admin_importa_proveedor y al resultado le hacemos substring. Usamos ese token ya que el ajax se ejecuta en el controlador adminimportaproveedor
            //console.log('token input '+$("input[id^='token_admin_importa_proveedor']").attr('id'));
            id_hiddeninput = $("input[id^='token_admin_importa_proveedor']").attr('id');
            //substring, desde 19 hasta final(si no se pone lenght coge el resto de la cadena)
            token_admin_importa_proveedor = id_hiddeninput.substr(30);
            //console.log(token_admin_importa_proveedor);

            //Si el formato de la referencia es correcto buscamos si ya existe en la BD
            $.ajax({
                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token_admin_importa_proveedor + "&action=comprobar_referencia" + '&ajax=1' + '&rand=' + new Date().getTime(),
                type: 'POST',
                data: dataObj,
                cache: false,
                dataType: 'json',
                success: function (data, textStatus, jqXHR)
                
                {
                    if (typeof data.error === 'undefined')
                    {
                        console.log('Referencia NO duplicada');
                        
                        showSuccessMessage(data.message);
                        $('#product_reference').css('background-color', '#8ece94');
                    }
                    else
                    {
                        //Mientras la referencia esté repetida mostrará el error 
                        
                        showErrorMessage(data.message);
                        //cambiamos el color al fondo del inpit
                        $('#product_reference').css('background-color', '#e6a5aa');
                    }

                },
                error: function (jqXHR, textStatus, errorThrown)
                {
                    showErrorMessage('ERRORS: ' + textStatus);
                    $('#product_reference').css('background-color', '#e6a5aa');
                }
            });          
         
        }else{
            console.log('La referencia NO es correcta');
            showErrorMessage('La referencia NO es correcta');
            $('#product_reference').css('background-color', '#e6a5aa');
        }           


    });
    
    //cuando se pulse el submit, validar los campos antes de darlo por bueno
    $('#configuration_form').on('submit', function(e){
        e.preventDefault();
        console.log('submit pulsado');
        error = 0;
        //input de nombre, referencia de producto, precio de coste, no pueden estar vacio
        if ($('#product_name').val() == ''){
            error = 1;
            alert('¡Debes introducir un nombre para el producto!');
        } 

        if ($('#product_reference').val() == ''){
            error = 1;
            alert('¡Debes poner referencia al producto! (AAA12345678)');
        } 

        if ($('#id_supplier').val() == 0){
            error = 1;
            alert('¡Debes seleccionar un proveedor!');
        } 

        if (($('#wholesale_price').val() == '') || !$.isNumeric($('#wholesale_price').val())) {
            error = 1;
            alert('¡Debes poner precio de coste al producto y este debe ser un número!');
        } 

        if (($('#sell_price').val() == '') || !$.isNumeric($('#sell_price').val())) {
            error = 1;
            alert('¡Debes poner precio de venta al producto y este debe ser un número!');
        } 

        //si hay algo, ean debe ser un número hasta 13 cifras
        if (($('#ean').val() != '') && !(/^[0-9]{0,13}$/.test($('#ean').val()))){
            error = 1;
            alert('¡El ean debe ser un número sin decimales de hasta 13 cifras!');
        }

        //input de referencia de proveedor no puede estar vacia si combinaciones no está checado (no tiene combinaciones). Si está checado, el resto de referencias de proveedor y producto no deben estar vacios
        if($('#producto_combinaciones_si').is(':checked')) {
            console.log('combi checado');
            //comprobamos que si está checado combinaciones, se haya seleccionado un grupo de atributos y algún número de combinaciones
            if (($('#attribute_group').val() == 0) || ($('#num_combination').val() == 0)) {
                error = 1;
                alert('¡Has marcado crear combinaciones pero no las has generado, hazlo o quita el check de combinaciones!');
            }
            error_select_atributo = 0;
            error_ref_comb = 0;
            error_ean_comb = 0;
            error_ref_prov_comb = 0;  
            //cada select de atributos tiene clase "atributos", los chequeamos uno a uno
            $('.atributos').each(function() {
                if (this.value == 0){
                    error = 1;
                    error_select_atributo = 1;                    
                }
            });

            if (error_select_atributo) {
                alert('¡Debes seleccionar un atributo para cada combinación del producto!');
            }

            //cada input de referencia de combinación tiene clase "referencia_combinacion", los chequeamos uno a uno. La referencia debe tener más de 12 caracteres, ya que será como mínimo la referencia de producto AAA12345678 y el guión -, es decir, debe comenzar por AAA12345678-, y no tener más de 32, pudiendo tener _ y -
            $('.referencia_combinacion').each(function() {
                if ( !/^[A-Za-z]{3}[0-9]{8}[-][A-Za-z0-9_-]{1,20}$/.test(this.value)){                
                    error = 1;
                    error_ref_comb = 1;                    
                }
            });

            if (error_ref_comb) {
                alert('¡Debes poner referencia a todas las combinaciones del producto, debe tener el formato adecuado, sin espacios, y un máximo de 32 caracteres!');
            }

            //cada input de ean de combinación tiene clase "ean_combinacion", los chequeamos uno a uno
            $('.ean_combinacion').each(function() {
                if ((this.value != '') && !(/^[0-9]{0,13}$/.test(this.value))){
                    error = 1;
                    error_ean_comb = 1;
                }
            });

            if (error_ean_comb) {
                alert('¡El ean de las combinaciones deben ser un número sin decimales de hasta 13 cifras!');
            }

            //cada input de referencia de proveedor de combinación tiene clase "referencia_proveedor_combinacion", los chequeamos uno a uno
            $('.referencia_proveedor_combinacion').each(function() {
                if (this.value == ''){
                    error = 1;
                    error_ref_prov_comb = 1;                    
                }
            });

            if (error_ref_prov_comb) {
                alert('¡Debes poner referencia de proveedor a todas las combinaciones del producto!');
            }


        } else {
            //si no hay combinaciones, avisa de introducir referencia proveedor
            if ($('#product_supplier_reference').val() == ''){
                error = 1;
                alert('¡Debes poner referencia de proveedor al producto!');
            } 
        }

        //si el check de imágenes está marcado, comprobar que al menos una tiene contenido, si no avisar. 
        if($('#producto_imagenes_si').is(':checked')) {
            console.log('imagenes checado');

            //hay 6 inputs de imagen, si los 6 están vacios mostramos error
            var error_imagen_vacia = 0;
            $('.imagenes').each(function() {
                if (!this.value){                    
                    error_imagen_vacia++;                    
                }
            });

            if (error_imagen_vacia == 6) {
                error = 1;
                alert('¡Has marcado imágenes pero no has introducido ninguna url!');
            }

            //evaluamos que el contenido de cada input de imagen, si no está vacío, sea una url válida
            // /^(http(s)?:\/\/)?(www\.)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/ para url con :8000 etc al final
            var error_formato_url = [];
            $('.imagenes').each(function() {
                // hacemos regex para que sea url válida que acabe en jpg, png o gif, que es lo que soporta prestashop
                if (this.value && !(/^(http(s)?:\/\/)?(www\.)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(\/.*)?([pP][nN][gG]|[jJ][pP][gG]|[gG][iI][fF])$/.test(this.value))) {
                    error = 1;
                    //en la variable array error_formato_url vamos almacenando el nº de imagen que corresponde al input con error (sacado el nº del id)
                    error_formato_url.push(this.id.substr(-1, 1));                  
                }
            });

            if (error_formato_url.length != 0) {
                //si hay algo dentro del array, lo recorremos y mostramos aviso por cada input que haya dado error
                error_formato_url.forEach(function(url){
                    alert('¡La url de la imagen '+url+' no es una url de imagen válida! Recuerda que Prestashop solo admite jpg, png y gif estáticos');
                });                
            }


        }


        console.log('error = '+error);
        if (!error) {
            //si error = 0 seguimos con la ejecución del formulario
            event.currentTarget.submit();
        }
        
    });

    //$("#mySelect").children().remove('optgroup[label=MyGroup2]'); QUITAR un grupo de select

});
