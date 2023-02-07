/**
 * Creador de productos Flash
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

 //escondemos el select para asignar cuantas combinaciones tendrá el producto y el de grupos de atributos hasta que se marque el checkbox de combinaciones. También escondemos el input para introducir el producto del que queremos calcar las categorías
$(document).ready(function(){
    $('#producto_importar').hide();
    $('#producto_importar').attr("placeholder", "Introduce la referencia completa del producto");
    $('#num_combination').hide();
    $('#attribute_group').hide(); 
    //02/09/2022 pasamos de peso 1.111 por defecto a 0.444
    $('#weight').val(0.444); 
    $('#meta_title').val('Producto por 34,90€ – LaFrikileria.com');
    $('#meta_description').val('Compra todos los artículos de - en nuestra tienda de regalos originales de Cine, Series de Tv, Videojuegos, Superhéroes, Manga y Anime');    
    //escondemos los inputs para url de imágenes y les ponemos placeholder
    $('.imagenes').hide();      
    $('.imagenes').attr("placeholder", "Otras imágenes - Introduce una url válida");
    $('#cover_image_1').attr("placeholder", "Imagen Principal - Introduce una url válida para la imagen por defecto del producto");
    //11/08/2021 deshabilitamos Cerdá tanto de fabricantes como de proveedores ya que solo deben crearse desde importador o yo los de kids para que estén bien configurados para ventas mediante conector
    //04/02/2022 Impedimos también seleccionar Globomatik
    $('#id_supplier option[value="65"]').prop('disabled', true); //proveedor Cerdá
    $('#id_supplier option[value="65"]').prop('title', 'Los productos Cerdá no se crean a mano');
    $('#id_supplier option[value="156"]').prop('disabled', true); //proveedor Globomatik
    $('#id_supplier option[value="156"]').prop('title', 'Los productos Globomatik no se crean a mano');
    $('#id_manufacturer option[value="58"]').prop('disabled', true); //fabricante Cerdá
    $('#id_manufacturer option[value="58"]').prop('title', 'Los productos Cerdá no se crean a mano');
    $('#id_manufacturer option[value="81"]').prop('disabled', true); //fabricante Cerdá Adult
    $('#id_manufacturer option[value="81"]').prop('title', 'Los productos Cerdá no se crean a mano');
    $('#id_manufacturer option[value="76"]').prop('disabled', true); //fabricante Cerdá Kids
    $('#id_manufacturer option[value="76"]').prop('title', 'Los productos Cerdá no se crean a mano'); 

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

        //si se marca el de producto a calcar
        if (this.id == 'check_importar_si') {         
            //si está checado mostramos el input para referencia, y si existe, el div con las categorías del producto
            if($(this).is(':checked')) {    
                $('#producto_importar').show();   
                if ($('.categorias_clonar').length) {
                    $('.categorias_clonar').show();   
                }                     
                
            }else{
                //si el check se quita, escondemos el input, si se vuelve a marcar, sigue ahí. y si existe, lo mismo para el div con las categorías del producto
                $('#producto_importar').hide();
                if ($('.categorias_clonar').length) {
                    $('.categorias_clonar').hide();   
                }  
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

                        $('#num_combination').closest('div').append("<div class='col-lg-7 combinaciones'><p><b>"+$('#attribute_group option:selected').text()+" "+i+"</b></p><p>"+select_atributos+"</p><p>Referencia Combinación <input class='referencia_combinacion' id='combination_reference_"+i+"' type='text' name='combination_reference_"+i+"' value='"+$('#product_reference').val()+"-'></p><p>Ean <input class='ean_combinacion input_ean' id='combination_ean_"+i+"' type='text' name='combination_ean_"+i+"'  placeholder='Ean combinación'></p><p>Referencia Proveedor <input class='referencia_proveedor_combinacion' id='combination_supplier_reference_"+i+"' type='text' name='combination_supplier_reference_"+i+"'  placeholder='Referencia de proveedor de combinación'></p><input type='hidden' id='hidden_attribute_"+i+"' name='hidden_attribute_"+i+"'></input><br></div>");
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
        var referencia_introducida = $("#product_reference").val().trim();            
            
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

    //10/08/2021 cuando se introduce un ean, para producto base , al hacer focusout, por ajax, usando la función del importador, comprobar que no esté repetido. En la comprobación de errores haremos lo mismo para los ean de atributo si hay, y miraremos si tiene formato numérico
    // $('#ean').focusout(function(){
    //para poder usarlo con todos los input de ean hemos añadido la clase input_ean a cada input, tanto al creado en el controlador como a los creados dinámicamnete. Como estos últimos no existen al cargar el documento, hay que utilizar $(document).on('focusout','.input_ean' para que los encuentre en lufgar de buscar directaemnete  $('.input_ean').focusout(function(){
    $(document).on('focusout','.input_ean',function() {    
        console.log(this.value);
        var ean_introducido = this.value; 
        var id_input = this.id;
        
        //comprobamos que el ean introducido tenga formato 12 o 13 números
        if (!(/^[0-9]{12,13}$/.test(ean_introducido))){
            showErrorMessage('El ean introducido no tiene formato de ean');
            //cambiamos el color al fondo del input
            $('#'+id_input).css('background-color', '#e6a5aa');
        } else {        
            var dataObj = {};
            dataObj['ean_introducido'] = ean_introducido;

            // obtenemos token del input hidden que hemos creado con id 'token_admin_importa_proveedor_'.$token_admin_importa_proveedor, para ello primero buscamos el id de un input cuyo id comienza por token_admin_importa_proveedor y al resultado le hacemos substring. Usamos ese token ya que el ajax se ejecuta en el controlador adminimportaproveedor
            //console.log('token input '+$("input[id^='token_admin_importa_proveedor']").attr('id'));
            id_hiddeninput = $("input[id^='token_admin_importa_proveedor']").attr('id');
            //substring, desde 19 hasta final(si no se pone lenght coge el resto de la cadena)
            token_admin_importa_proveedor = id_hiddeninput.substr(30);
            //console.log(token_admin_importa_proveedor);

            //Si el formato del ean es correcto buscamos si ya existe en la BD
            $.ajax({
                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token_admin_importa_proveedor + "&action=comprobar_ean" + '&ajax=1' + '&rand=' + new Date().getTime(),
                type: 'POST',
                data: dataObj,
                cache: false,
                dataType: 'json',
                success: function (data, textStatus, jqXHR)
                
                {
                    if (typeof data.error === 'undefined')
                    {
                        // console.log('Ean NO duplicada');
                        
                        showSuccessMessage(data.message);
                        $('#'+id_input).css('background-color', '#8ece94');
                    }
                    else
                    {
                        //Mientras el ean esté repetido mostrará el error 
                        // console.log(data.duplicado);
                        showErrorMessage(data.message);
                        //cambiamos el color al fondo del inpit
                        $('#'+id_input).css('background-color', '#e6a5aa');
                        //mostramos un alert con la referencia o referencias de los productos que tengan el ean
                        var alert_ean = 'Ean '+ean_introducido+' existente en:\r\r';
                        Object.entries(data.duplicado).forEach(([key, value]) => {
                            // console.log(value);
                            if (value.atributo == 0) {
                                alert_ean += 'Producto '+value.referencia_ean+'\r';
                            } else {
                                alert_ean += 'Atributo '+value.referencia_atributo+'\r';
                            }
                            
                        });

                        alert(alert_ean);
                        
                    }

                },
                error: function (jqXHR, textStatus, errorThrown)
                {
                    showErrorMessage('ERRORS: ' + textStatus);
                    $('#'+id_input).css('background-color', '#e6a5aa');
                }
            });          
         
        }         

    });

    //20/10/2021 
    //cuando se introduce una referencia para el producto del que queremos copiar las categorías y descripción, al hacer focusout, comprobar que la referencia tenga el formato adecuado y por ajax, buscar el producto, obtener sus datos por ajax.
    $('#producto_importar').focusout(function(){
        var referencia_importar = $("#producto_importar").val().trim();            
            
        //comprobamos que la referencia introducida tenga formato AAA11111111
        if (/^[A-Za-z]{3}[0-9]{8}$/.test(referencia_importar)){
            console.log('La referencia ES correcta');
            var dataObj = {};
            dataObj['referencia_importar'] = referencia_importar;
            console.log(dataObj);
            console.log(dataObj['referencia_importar']);

            //Si el formato de la referencia es correcto buscamos si ya existe en la BD, si es así traeremos aquí sus categorías y decripción corta, SEO y tipo de producto.
            $.ajax({
                url: 'index.php?controller=AdminCrearProductos' + '&token=' + token + "&action=comprobar_referencia_importar" + '&ajax=1' + '&rand=' + new Date().getTime(),
                type: 'POST',
                data: dataObj,
                cache: false,
                dataType: 'json',
                success: function (data, textStatus, jqXHR)
                
                {
                    if (typeof data.error === 'undefined')
                    {
                        // console.log('Referencia NO duplicada');
                        // console.log(data.category_default);
                        // console.log(data.ids_categorias);
                        // console.log(data.categorias);
                        // console.log(data.descripciones);
                        // console.log(data.feature_value);
                                                
                        showSuccessMessage(data.message);
                        $('#producto_importar').css('background-color', '#8ece94');

                        //rellenamos los campos de nombre, descripción y SEO y cambiamos el select de tipo de producto a lo que nos ha llegado via ajax
                        $('#product_name').val(data.descripciones.name);
                        $('#long_description').val(data.descripciones.description_short);
                        $('#meta_description').val(data.descripciones.meta_description);
                        $('#meta_title').val(data.descripciones.meta_title);
                        //el select
                        $('#id_feature_value option[value='+data.feature_value.id_feature_value+']').attr('selected','selected');

                        //Creamos una presentación de las categorías del producto, con la principal y el resto. Estas categorías se añadirán al producto a crear
                        //lo ponemos todo debajo del input de referencia del producto a copiar
                        //pondremos un input hidden con la categoría por defecto y otro con todas las categorias, que se leeran vía post desde el controlador si el check de importar características está marcado
                        //primero nos aseguramos de eliminar cualquier div clase categorias_clonar por si se hace varias veces para que no se repita
                        $('.categorias_clonar').remove();
                        //hacemos append de lo que queremos mostrar                        
                        $('#producto_importar').closest('div').append(`
                        <div class="form-group categorias_clonar">
                        <br>
                            <label class="control-label col-lg-3">
                                Categoría principal
                            </label>
                            <label class="control-label">
                                <b>${data.category_default.name}</b>
                            </label> 
                            <input type='hidden' id='id_category_default' name='id_category_default' value="${data.category_default.id_category}"></input>                           
                        </div>`);

                        var categorias_secundarias = '';

                        Object.entries(data.categorias).forEach(([key, value]) => {
                            categorias_secundarias +=  '<li>'+value.name+'</li>';
                        });

                        var panel_categorias_secundarias = `
                        <div class="form-group categorias_clonar">
                            <label class="control-label col-lg-3">
                                Categorías secundarias
                            </label>
                            <div class="form-group col-lg-6">
                                <div class="panel" id="panel_categorias">
                                    <ul>
                                        ${categorias_secundarias}
                                    </ul> 
                                </div> 
                            </div> 
                            <input type='hidden' id='ids_categorias' name='ids_categorias' value="${data.ids_categorias}"></input>                        
                        </div>`;

                        $('#producto_importar').closest('div').append(panel_categorias_secundarias);

                    }
                    else
                    {
                        //Mientras la referencia no sea correcta mostrará el error 
                        
                        showErrorMessage(data.message);
                        //cambiamos el color al fondo del inpit
                        $('#producto_importar').css('background-color', '#e6a5aa');
                    }

                },
                error: function (jqXHR, textStatus, errorThrown)
                {
                    showErrorMessage('ERRORS: ' + textStatus);
                    $('#producto_importar').css('background-color', '#e6a5aa');
                }
            });          
         
        }else{
            console.log('La referencia NO es correcta');
            showErrorMessage('La referencia NO es correcta');
            $('#producto_importar').css('background-color', '#e6a5aa');
        }           


    });
    //fin mod 20/10/2021 - calcar producto
    
    //cuando se pulse el submit, validar los campos antes de darlo por bueno
    $('#configuration_form').on('submit', function(e){
        e.preventDefault();
        console.log('submit pulsado');
        error = 0;

        //si el check de Importar características está chechado, tenemos que coger las categorías del producto seleccionado para añadirlas al producto que vamos a crear.
        // if($('#check_importar_si').is(':checked')) {
        //     console.log('check importar checado');
        //     console.log('cat por defecto='+$('#id_category_default').val());
        //     console.log('otras categorias='+$('#ids_categorias').val());
        // }

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

            //cada input de referencia de combinación tiene clase "referencia_combinacion", los chequeamos uno a uno. La referencia debe tener más de 12 caracteres, ya que será como mínimo la referencia de producto AAA12345678 y el guión -, es decir, debe comenzar por AAA12345678-
            $('.referencia_combinacion').each(function() {
                if ( !/^[A-Za-z]{3}[0-9]{8}[-]/.test(this.value)){
                    error = 1;
                    error_ref_comb = 1;                    
                }
            });

            if (error_ref_comb) {
                alert('¡Debes poner referencia a todas las combinaciones del producto y debe tener el formato adecuado!');
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
            //para evitar dobles clicks y que se creen productos duplicados por error deshabilitamos el botón tras la pulsación cuando todo es correcto
            $('#configuration_form_submit_btn').attr("disabled", "disabled");
            event.currentTarget.submit();
        }
        
    });

    //$("#mySelect").children().remove('optgroup[label=MyGroup2]'); QUITAR un grupo de select

});
