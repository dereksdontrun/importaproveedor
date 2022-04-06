/**
 * Importador de productos desde catálogo de proveedor
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

$(function(){
   $('#product_form').on('submit',function(event){
       event.preventDefault();
       //dataObj se crea ={} porque es un objeto. Con =[] sería array
       var dataObj = {};
       //declaramos aquí también el array de ids de producto (input hidden en form.tpl) 
       product_ids = [];
       
       //guardamos en dataObj el contenido de select e inputs del formulario
       $(this).find('input, select').each(function(index, elt){
           dataObj[elt.name] = elt.value;
       });

       $.ajax({
        url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=get_products" + '&ajax=1' + '&rand=' + new Date().getTime(),
        type: 'POST',
        data: dataObj,
        cache: false,
        dataType: 'json',
        success: function (data, textStatus, jqXHR)
        {
            $('#products_in_search > tbody').html('');
            if (typeof data.error === 'undefined')
            {
                $('#product_ids').val();
                //vaciar el array del input hidden para que el número de productos mostrado sea correcto
                product_ids = []; 
                //Sacamos de data.products cada línea (producto) resultante de la consulta y enviamos los datos a addProduct (en form.tpl)
                $.each(data.products, function(index, line){
                    addProduct(line);
                });
                $('#products_in_search > tbody tr:eq(0) input:eq(0)').trigger('focus').select();
            }
            else
            {
                $('#num_prods').text('');
                showErrorMessage(data.message);
            }
        },
        error: function (jqXHR, textStatus, errorThrown)
        {
            showErrorMessage('ERRORS: ' + textStatus);
        }
    });
   });

   // bind enter key event on inputs location and ean
   //Si se pulsa Enter (en lugar de pulsar con ratón en Buscar) hace Submit
    $('#products_in_search> tbody').on('keypress', 'input[type="text"]',function(e) {
        var code = (e.keyCode ? e.keyCode : e.which);
        if(code == 13) { //Enter keycode
            e.stopPropagation();//Stop event propagation
            $(this).parents('tr:eq(0)').find('button.update').trigger('click');
            return false;
        }
    });

    //Jquery que muestre un mensaje cuando el ratón pasa sobre el botón de + Añadir proveedor
    $('#products_in_search> tbody').on('hover', 'button.agregar', function(e){
      $('button.agregar').attr('title', '¡¡ASEGURATE DE QUE EL PRODUCTO EN PRESTASHOP CORRESPONDE AL PRODUCTO DEL PROVEEDOR!!');      
    });   

    //Jquery que muestre un mensaje cuando el ratón pasa sobre el EAN repetido
    $('#products_in_search> tbody').on('hover', '.ean_repetido', function(e){
      $('.ean_repetido').attr('title', 'El EAN correspondiente a esta línea se encuentra en varios productos o atributos de Prestashop, comprueba antes de añadirlo');    
    });   

    //CREAR PRODUCTO
    //jquery recoge si se pulsa un botón con clase crear y dispara esta función que identificará el producto y llamará mediante ajax a la ejecución de la función crearproducto
    $('#products_in_search> tbody').on('click', 'button.crear', function(e){
        //line recoje, los valores de los campos del tr en cuyo botón se ha pulsado
        var line = $(this).parents('tr:eq(0)');
        var dataObj = {};
        //la función va añadiendo a dataObj[] el nombre del input (name) y le asigna el value dentro del input
        line.find('input').each(function(index, elt){
            dataObj[elt.name] = elt.value;
        });
        //el atributo id del botón está formado por crear|idProducto, guardamos 
        //la parte derecha [1], que es el id del producto en la tabla import_catalogos, id_import_catalogos
        var ids = $(this).attr('id').split('|');
        dataObj['id'] = ids[1];

        //sacamos el nombre del producto en esa línea
        var nombre_linea_producto = $('#nombre_linea_'+dataObj['id']).text();

        //inicializamos el cuadro de diálogo(jquery-ui) oculto
        $( "#dialog" ).dialog({ 
            autoOpen: false, 
            modal : true, 
            //dialogClass: "no-close",
            closeOnEscape: false,
            width: "50%",
            show : "blind", 
            hide : "blind"            
        });

        // if ($('#dialog').dialog('isOpen') === true) {
        //     $("#dialog").dialog("destroy");
        // }

        //mostramos el diálogo que pide la referencia para el nuevo producto
        $("#dialog").dialog("open");    
        //rellenamos el input de nombre con el nombre de la línea seleccionada
        $("#dialog_nombre_producto").val(nombre_linea_producto);


        //Jquery que cuando se pulse un botón de la clase ui-button-titlebar-close (la x de la esquina) esconda los mensajes de error del dialog y limpie el 
        //input text de modo que se "resetee", si no, cada vez que se abre muestra el contenido anterior
        $(':button.ui-dialog-titlebar-close').on('click', function(e){
            ////console.log('pulsado X ui button');
            $("#error_ref_repetida").hide();
            $("#error_formato_ref").hide();
            $("#referencia_nuevo").val('');
            $("#producto_importar").val('');
            $('#producto_importar').hide();
            $("#check_importar").removeAttr('checked'); //quitamos check de "copiar característics"
            $('#categorias_clonar').empty(); //en caso de haber buscado un producto para copiar, vaciamos los resultados            
            $("#dialog_nombre_producto").val('');
        });

        //Cuando el usuario pulsa en el botón de crear, lanzamos la función que chequeará la referencia y si es correcto lanzará la creación del producto
        //ponemos .off() primero para no "acumular" pulsaciones y que no repita productos
        $('#dialog_submit_referencia').off('click').on('click', function(e){
            ////console.log($("#referencia_nuevo").val());
            var referencia_introducida = $("#referencia_nuevo").val();
            var nombre_producto = $("#dialog_nombre_producto").val();  
            dataObj['nombre_producto'] = nombre_producto;  
            //metemos si el check de "copiar características" está marcado, si no no va por post
            // console.log('check importar??='+ $("#check_importar:checked" ).val());
            dataObj['check_importar'] = $("#check_importar:checked" ).val();  
            dataObj['id_producto_copiar'] = $("#id_producto_copiar" ).val();   //metemos el id del producto que se introdujo en el input de referencia para copiar datos
            dataObj['id_category_default'] = $("#id_category_default" ).val();
            dataObj['ids_categorias'] = $("#ids_categorias" ).val();
            
            //comprobamos que la referencia introducida tenga formato AAA11111111
            if (/^[A-Za-z]{3}[0-9]{8}$/.test(referencia_introducida)){
                //console.log('La referencia ES correcta');
                $("#error_formato_ref").hide();

                dataObj['referencia'] = referencia_introducida;
                //Si el formato de la referencia es correcto buscamos si ya existe en la BD
               $.ajax({
                    url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=comprobar_referencia" + '&ajax=1' + '&rand=' + new Date().getTime(),
                    type: 'POST',
                    data: dataObj,
                    cache: false,
                    dataType: 'json',
                    success: function (data, textStatus, jqXHR)
                    {
                        if (typeof data.error === 'undefined')
                        {
                            //console.log('Referencia NO existente');
                            $("#error_ref_repetida").hide();
                            showSuccessMessage(data.message);

                            //Si la referencia es correcta y no está repetida creamos el producto
                            //dataObj contiene el id del producto en la tabla import_catalogos y la referencia introducida
                            
                            $.ajax({
                                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=crear_producto" + '&ajax=1' + '&rand=' + new Date().getTime(),
                                type: 'POST',
                                data: dataObj,
                                cache: false,
                                dataType: 'json',
                                success: function (data, textStatus, jqXHR)
                                {
                                    if (typeof data.error === 'undefined')
                                    {
                                        //Si se crea el producto correctamente limpiamos y cerramos el cuadro dialog
                                        $("#error_ref_repetida").hide();
                                        $("#error_formato_ref").hide();
                                        $("#referencia_nuevo").val('');
                                        $("#producto_importar").val('');
                                        $('#producto_importar').hide();
                                        $("#check_importar").removeAttr('checked'); 
                                        $('#categorias_clonar').empty();
                                        $("#dialog").dialog("close"); 

                                        //también quitamos el botón de crear producto
                                        var id_boton = '#botoncito_'+dataObj['id'];                                        
                                        $(id_boton).html('<span class="title"><i class="icon-question-sign"></i> Producto recién creado</span>');                       

                                        showSuccessMessage(data.message);                        
                                        
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
                            });                       
                            

                        }
                        else
                        {
                            //Mientras la referencia esté repetida mostrará el error 
                            $("#error_ref_repetida").show();
                            showErrorMessage(data.message);
                        }

                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        showErrorMessage('ERRORS: ' + textStatus);
                    }
                });          
             
            }else{
                //console.log('La referencia NO es correcta');
                $("#error_formato_ref").show();
            }

            


        });
        
    //Cuando se actualiza o crea producto (onclick), un scroll te devuelve a la parte de arriba del formulario
    //jQuery('html, body').animate({scrollTop: $("#product_form").offset().top-300}, {duration: 500, specialEasing: {width: "linear", height: "easeOutBounce"}});
        
        
    });

    //AÑADIR PROVEEDOR A PRODUCTO EXISTENTE
    //jquery recoge si se pulsa un botón con clase agregar y dispara esta función que identificará el producto y llamará mediante ajax a la ejecución de la función agregarproveedor
    $('#products_in_search> tbody').on('click', 'button.agregar', function(e){
        //line recoje, los valores de los campos del tr en cuyo botón se ha pulsado
        var line = $(this).parents('tr:eq(0)');
        var dataObj = {};
        //la función va añadiendo a dataObj[] el nombre del input (name) y le asigna el value dentro del input
        line.find('input').each(function(index, elt){
            dataObj[elt.name] = elt.value;
        });
        //el atributo id del botón está formado por agregar|idProducto, guardamos 
        //la parte derecha [1], que es el id del producto en la tabla import_catalogos, id_import_catalogos
        var ids = $(this).attr('id').split('|');
        dataObj['id'] = ids[1];
        
        //Cuando se actualiza o crea producto (onclick), un scroll te devuelve a la parte de arriba del formulario
        //jQuery('html, body').animate({scrollTop: $("#product_form").offset().top-300}, {duration: 500, specialEasing: {width: "linear", height: "easeOutBounce"}});
        
        //Se lanza el ajax a ejecutar la función agregar_proveedor en AdminImportProveedor.php - ajaxProcessAgregarProveedor()
        $.ajax({
            url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=agregar_proveedor" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            {
                if (typeof data.error === 'undefined')
                {                    
                    showSuccessMessage(data.message)

                    //también quitamos el botón de Añadir proveedor
                    var id_boton = '#botoncito_'+dataObj['id'];                                        
                    $(id_boton).html('<span class="title"><i class="icon-question-sign"></i> Proveedor recién añadido</span>');
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
        });
    });

    //Jquery que cuando se marca un checkbox de atributos cambia el botón de esa línea de producto por un botón de Crear producto con atributos, así para cada checkbox marcado, y si se desmarca, volvemos al botón que tuviera.         
    $('#products_in_search> tbody').on('change', 'input[type="checkbox"]', function(e){
        var id = $(this).attr('id').split('|');
        //console.log("check checado "+id[1]);
        var id_boton = '#botoncito_'+id[1];      
        

        if($(this).is(':checked')) {
            //console.log("está checado");  

            //si el check al hacer change está checado, ponemos el botón de crear atributos                                                   
            $(id_boton).html('<button type="button" id="crear_atributos|'+id[1]+'" class="btn btn-default crearatributos"><i class="icon-list-ul"></i> Crear Producto<br>con Atributos</button>');
        }else{
            //console.log("NO está checado");
            //si el check al hacer change NO está checado, ponemos el botón que había antes, que tendría que ser el original, lo reconstruimos    
            var boton_antes = '<button type="button" id="crear|'+id[1]+'" class="btn btn-default crear"><i class="icon-save"></i> Crear Producto </button>';                    $(id_boton).html(boton_antes);
        }
        
        
    });

    //CREAR PRODUCTO CON ATRIBUTOS
    //jquery recoge si se pulsa un botón con clase crearatributos y dispara esta función que identificará todas las líneas con el check marcado y llamará mediante ajax a la ejecución de la función crearproductoatributos
    $('#products_in_search> tbody').on('click', 'button.crearatributos', function(e){
        var id_checkbox = '';
        var dataObj = {};
        var ids_productos_catalogo = [];
        var nombre_linea_producto = '';
        //buscamos todos los checkbox checados y almacenamos los id_import_catalogos de cada uno
        $('input[type=checkbox]:checked').each(function () {
            id_checkbox = $(this).attr('id').split('|');
            //sacamos el id del producto que es la parte derecha del | id del checkbox 
            ids_productos_catalogo.push(id_checkbox[1]);
            //guardamos el nombre del producto para rellenar el input cuando pedimos nombre para nuevo producto, da igual que guardemos el último. Lo sacamos del text del <td> clase nombre_linea, un span que contiene el nombre
            nombre_linea_producto = $('#nombre_linea_'+id_checkbox[1]).text(); 
        });
        //guardamos el array de ids en el array dataObj que se enviará con Ajax a la función crearproductoatributos
        dataObj['ids'] = ids_productos_catalogo;
        //console.log(dataObj);
        // //console.log(dataObj['ids'][2]);

        //inicializamos el cuadro de diálogo(jquery-ui) oculto
        $( "#dialog" ).dialog({ 
            autoOpen: false, 
            modal : true, 
            //dialogClass: "no-close",
            closeOnEscape: false,
            width: "50%",
            show : "blind", 
            hide : "blind"            
        });       

        //mostramos el diálogo que pide la referencia y nombre para el nuevo producto
        $("#dialog").dialog("open");   
        //rellenamos el input de nombre con el nombre de uno de los seleccionados para formar el producto
        $("#dialog_nombre_producto").val(nombre_linea_producto);

        //Jquery que cuando se pulse un botón de la clase ui-button-titlebar-close (la x de la esquina) esconda los mensajes de error del dialog y limpie el 
        //input text de modo que se "resetee", si no, cada vez que se abre muestra el contenido anterior
        $(':button.ui-dialog-titlebar-close').on('click', function(e){
            //console.log('pulsado X ui button');
            $("#error_ref_repetida").hide();
            $("#error_formato_ref").hide();
            $("#referencia_nuevo").val('');
            $("#producto_importar").val('');
            $('#producto_importar').hide();
            $("#check_importar").removeAttr('checked'); //quitamos check de "copiar característics"
            $('#categorias_clonar').empty();
            $("#dialog_nombre_producto").val('');
            
        });

        //Cuando el usuario pulsa en el botón de comprobar, lanzamos la función que chequeará la referencia y si es correcto tendremos que mostrar un select con los grupos de atributos. Cuando se escoja uno, se deberán mostrar tantos inputs como checkbox se han marcado para los atributos, un input por referencia, y un select con los atributos que corresponden al grupo de atributos por cada atributo
        //ponemos .off() primero para no "acumular" pulsaciones y que no repita productos
        $('#dialog_submit_referencia').off('click').on('click', function(e){
            e.preventDefault();
            //console.log($("#referencia_nuevo").val());
            var referencia_introducida = $("#referencia_nuevo").val();  
            var nombre_producto = $("#dialog_nombre_producto").val();          
            
            //comprobamos que la referencia introducida tenga formato AAA11111111
            if (/^[A-Za-z]{3}[0-9]{8}$/.test(referencia_introducida)){
                //console.log('La referencia ES correcta');
                $("#error_formato_ref").hide();

                dataObj['referencia'] = referencia_introducida;
                //console.log(dataObj);
                //console.log(dataObj['referencia']);
                
                //Si el formato de la referencia es correcto buscamos si ya existe en la BD
               $.ajax({
                    url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=comprobar_referencia" + '&ajax=1' + '&rand=' + new Date().getTime(),
                    type: 'POST',
                    data: dataObj,
                    cache: false,
                    dataType: 'json',
                    success: function (data, textStatus, jqXHR)
                    
                    {
                        if (typeof data.error === 'undefined')
                        {
                            //console.log('Referencia NO existente');
                            $("#error_ref_repetida").hide();
                            showSuccessMessage(data.message);
                            //Si la referencia es correcta limpiamos y cerramos el cuadro dialog, ya que ya tenemos el dato y vamos a abrir un nuevo cuadro de dialog
                            $("#error_ref_repetida").hide();
                            $("#error_formato_ref").hide();
                            $("#referencia_nuevo").val('');
                            $("#dialog_nombre_producto").val('');
                            $("#dialog").dialog("close"); 

                            //Si la referencia es correcta y no está repetida tendremos que mostrar un select con los grupos de atributos
                            //dataObj contiene los ids de los productos en la tabla import_catalogos que serán unidos como atributos en un solo producto en Prestashop,y la referencia introducida
                            //14/07/2020 llamamos por ajax para sacar los grupos de atributos
                            $.ajax({
                                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=grupos_atributos" + '&ajax=1' + '&rand=' + new Date().getTime(),
                                type: 'POST',
                                data: dataObj,
                                cache: false,
                                dataType: 'json',
                                success: function (data, textStatus, jqXHR)
                                {
                                    if (typeof data.error === 'undefined')
                                    {
                                        //console.log(data.lista_grupos_atributos);       
                                        //console.log(dataObj);    
                                        //mostraremos el segundo dialog, donde crearemos el select cin los grupos de atributos
                                        //inicializamos el cuadro de diálogo(jquery-ui) oculto
                                        $( "#dialog_atributos" ).dialog({ 
                                            autoOpen: false, 
                                            modal : true, 
                                            //dialogClass: "no-close",
                                            closeOnEscape: false,
                                            width: "50%",
                                            show : "blind", 
                                            hide : "blind"            
                                        });       

                                        //ocultamos la frase y el botón de momento, y por si acaso vaciamos y regeneramos todo en el dialog
                                        $("#texto_2_atributos").hide();
                                        $("#dialog_submit_combinaciones").hide();
                                        $(".combinaciones").remove();
                                        $("#span_grupo_atributos").empty();
                                        $("#texto_2_atributos").hide();                                            
                                        $("#id_attribute_group").remove();
                                        $("#span_combinaciones").empty();
                                        //mostramos el diálogo que pide la referencia para el nuevo producto
                                        $("#texto_1_atributos").show();
                                        $("#dialog_atributos").dialog("open");                                          

                                        //Jquery que cuando se pulse un botón de la clase ui-button-titlebar-close (la x de la esquina) esconda los mensajes de error del dialog y limpie el 
                                        //input text de modo que se "resetee", si no, cada vez que se abre muestra el contenido anterior
                                        $(':button.ui-dialog-titlebar-close').on('click', function(e){
                                            //console.log('pulsado X ui button');
                                            $(".combinaciones").remove();
                                            $("#span_grupo_atributos").empty();
                                            $("#texto_2_atributos").hide();                                            
                                            $("#id_attribute_group").remove();
                                            $("#span_combinaciones").empty();
                                            
                                        });   
                                        
                                        //creamos el select que vamos a colocar en el dialog
                                        var select_grupo_atributos = '<select id="attribute_group" class="grupo_atributos select-css" name="attribute_group" style="display: block;">'
                                        Object.entries(data.lista_grupos_atributos).forEach(([key, value]) => {
                                            select_grupo_atributos +=  '<option value="'+value.id_attribute_group+'">'+value.name+'</option>';
                                        });                                        
                                        select_grupo_atributos += '</select>';
                                        //lo añadimos al dialog dentro del span id span_grupo_atributos
                                        $("#id_attribute_group").show();
                                        $('#span_grupo_atributos').append(select_grupo_atributos);

                                        //cuando se selecciona un grupo ocultamos el texto de selecciona blabla, mostramos el segundo texto y buscamos por ajax los atributos que corresponden al grupo, para mostrar un select por cada combinación
                                        $('#attribute_group').on('change', function(e){   
                                            //cada vez que se cambie el grupo de atributos, vaciamos todo lo generado si lo hay
                                            $("#span_combinaciones").empty();                                         
                                            dataObj['id_attribute_group'] = $('#attribute_group').val();
                                            
                                            //console.log(dataObj);
                                            //console.log(dataObj['id_attribute_group']);

                                            //mediante ajax pedimos los atributos que corresponden al grupo, id y nombre
                                            $.ajax({
                                                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=atributos_grupo" + '&ajax=1' + '&rand=' + new Date().getTime(),
                                                type: 'POST',
                                                data: dataObj,
                                                cache: false,
                                                dataType: 'json',
                                                success: function (data, textStatus, jqXHR)
                                                
                                                {
                                                    if (typeof data.error === 'undefined')
                                                    {
                                                        //escondemos un mensaje y mostramos el otro
                                                        $("#texto_1_atributos").hide();
                                                        $("#id_attribute_group").hide();
                                                        $("#texto_2_atributos").show();
                                                        //mostramos botón
                                                        $("#dialog_submit_combinaciones").show();

                                                        //console.log('Atributos encontrados');
                                                        //recibimos via ajax en data.lista_atributos la lista de id_attribute con sus nombres para el grupo de atributos
                                                        //console.log(data.lista_atributos);
                                                        //console.log('numero combinaciones= '+dataObj.ids.length);
                                                        dataObj['lista_atributos'] = data.lista_atributos;

                                                        //hacemos otro ajax para obtener datos de las líneas de producto donde se ha marcado el check
                                                        $.ajax({
                                                            url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=info_atributos_seleccionados" + '&ajax=1' + '&rand=' + new Date().getTime(),
                                                            type: 'POST',
                                                            data: dataObj,
                                                            cache: false,
                                                            dataType: 'json',
                                                            success: function (data, textStatus, jqXHR)
                                                            
                                                            {
                                                                if (typeof data.error === 'undefined')
                                                                {
                                                                    
                                                                    //ahora que tenemos todo, tenemos que generar en el cuadro de dialogo una línea por combinación que contenga los datos de la combinación (ref proveedor, ean y atributo si lo tiene), un input para la referencia que se le va a asignar a la combinación y un select con los atributos correspondientes al grupo de atributos escogido antes
                                                                    var num_combinacion = 0;
                                                                    var datos_combinaciones = data.datos_combinaciones;
                                                                    //console.log(datos_combinaciones);
                                                                    var i = '';
                                                                    Object.entries(datos_combinaciones).forEach(([key, value]) => {
                                                                        i = value.id_import_catalogos;
                                                                        num_combinacion++;
                                                                        //generamos cada línea
                                                                        var select_atributos = '<select id="attribute_'+i+'" class="atributos select-css" name="attribute_'+i+'" style="display: block;"><option value="0">Escoge '+$('#attribute_group option:selected').text()+'</option>'
                                                                        Object.entries(dataObj['lista_atributos']).forEach(([k, v]) => {
                                                                            select_atributos +=  '<option value="'+k+'">'+v+'</option>';
                                                                        });
                                                                        
                                                                        select_atributos += '</select>';

                                                                        $('#span_combinaciones').append("<div class='combinaciones' id='combinacion_"+i+"'><p><span  class='familia_atributo'><b>"+$('#attribute_group option:selected').text()+" "+num_combinacion+"</b></span></p><p>Ref Proveedor: "+value.referencia_proveedor+"</p></p><p>Ean13: "+value.ean+"</p><p>Atributo: "+value.atributo+"</p><p>"+select_atributos+"</p><p><span  class='familia_atributo'>Referencia Combinación <input class='referencia_combinacion' id='combination_reference_"+i+"' type='text' name='combination_reference_"+i+"' value='"+dataObj.referencia+"-'></span></p><input type='hidden' id='hidden_attribute_"+i+"' name='hidden_attribute_"+i+"'></input><br></div>");
                                                                    });  

                                                                    //añadimos un input hidden con la referencia del producto guardada para sacarla cuando enviemos todos los datos por ajax para crear el producto, y otor con el nombre introducido
                                                                    $('#span_combinaciones').append('<input type="hidden" id="referencia_nuevo_producto" value="'+dataObj.referencia+'"><input type="hidden" id="nombre_nuevo_producto" value="'+nombre_producto+'">');
                                                                    
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
                                                                        //console.log('previo '+atributo_previo);
                                                                        //console.log('seleccionado '+atributo_seleccionado);

                                                                        $('.atributos').each(function() {
                                                                            //por cada select, volvemos a poner disabled false en el atributo_previo, en todos, y lo quitamos en el atributo_seleccionado, en todos menos en el que estamos 
                                                                            $('.atributos').find('option[value="' + atributo_previo + '"]').prop('disabled', false);                          
                                                                            $('.atributos').not(this).find('option[value="' + atributo_seleccionado + '"]').prop('disabled', true);                      
                                                                        });
                                                                        
                                                                        //metemos en el input hidden para cada select el value de este
                                                                        combinacion = this.id.split('_');
                                                                        $('#hidden_attribute_'+combinacion[1]).val(atributo_seleccionado);
                                                                        //para ahorrar tiempo, al seleccionar un atributo, concatenamos el texto del mismo a la referencia de producto. Como se puede cambiar de atributo varias veces, en realidad no concatenamos sino que creamos de nuevo la referencia añadiendo a la referencia el guión y el atributo cada vez que cambie el select. Con atribtuos largos tendrán que editarlos manualmente o quitar espacios
                                                                        
                                                                        texto_atributo = $('#attribute_'+combinacion[1]+' option:selected').text();
                                                                        $('#combination_reference_'+combinacion[1]).val(dataObj.referencia+'-'+texto_atributo);
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

                                       
                                        });// fin onchange de select de  attribute group

                                        
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
                            });                                 

                        }
                        else
                        {
                            //Mientras la referencia esté repetida mostrará el error 
                            $("#error_ref_repetida").show();
                            showErrorMessage(data.message);
                        }

                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        showErrorMessage('ERRORS: ' + textStatus);
                    }
                });          
             
            }else{
                //console.log('La referencia NO es correcta');
                $("#error_formato_ref").show();
            }           


        });

    });

    //cuando se pulse el botón de Crear en el dialog de productos con atributos, recogemos todos los valores y por ajax enviamos a la función para crear el producto
    $('#dialog_submit_combinaciones').off('click').on('click', function(e){
        e.preventDefault();
        var error = 0;
        var error_select_atributo = 0;
        var error_ref_comb = 0;
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

        //cada input de referencia de combinación tiene clase "referencia_combinacion", los chequeamos uno a uno. La referencia debe tener más de 12 caracteres, ya que será como mínimo la referencia de producto AAA12345678 y el guión -, es decir, debe comenzar por AAA12345678- , y no tener más de 32, pudiendo tener _ y -
        //también la / poniendo primero \ para escaparla
        $('.referencia_combinacion').each(function() {
            if ( !/^[A-Za-z]{3}[0-9]{8}[-][A-Za-z0-9_\/-]{1,20}$/.test(this.value)){
                error = 1;
                error_ref_comb = 1;                    
            }
        });

        if (error_ref_comb) {
            alert('¡Debes poner referencia a todas las combinaciones del producto, debe tener el formato adecuado, sin espacios, y un máximo de 32 caracteres!');
        }

        //si no hay errores cogemos los valores para crear el producto
        if (!error) {   
            //11/08/2021 para evitar dobles clicks y que se creen productos duplicados por error deshabilitamos el botón tras la pulsación cuando todo es correcto, dado que .off('click') no parece funcionar bien aquí
            $('#dialog_submit_combinaciones').attr("disabled", "disabled");         
            var dataObj = {};
            dataObj['referencia'] = $('#referencia_nuevo_producto').val(); 
            dataObj['nombre_producto'] = $('#nombre_nuevo_producto').val();     
            //metemos si el check de "copiar características" está marcado, si no no va por post. Todo esto quedó oculto en el primer dialog 
            // console.log('check importar??='+ $("#check_importar:checked" ).val());
            dataObj['check_importar'] = $("#check_importar:checked" ).val();
            dataObj['id_producto_copiar'] = $("#id_producto_copiar" ).val();   //metemos el id del producto que se introdujo en el input de referencia para copiar datos
            dataObj['id_category_default'] = $("#id_category_default" ).val();
            dataObj['ids_categorias'] = $("#ids_categorias" ).val();
                             
            //por cada div creado con su select, etc sacamos los valores             
            var combinaciones = new Array();          
            
            $('.combinaciones').each(function() {
                id_import_catalogos = this.id.split('_');
                var referencia_combinacion = $('#combination_reference_'+id_import_catalogos[1]).val();
                // console.log(referencia_combinacion);
                //quitamos espacios en blanco de ref si no lo ha hecho el usuario
                referencia_combinacion = referencia_combinacion.replace(/\s/g, ''); 
                // console.log(referencia_combinacion);
                var combinacion = {'id_import_catalogos' : id_import_catalogos[1], 
                                    'id_atributo' : $('#hidden_attribute_'+id_import_catalogos[1]).val(),
                                    'referencia_combinacion' : referencia_combinacion }; 
                //metemos cada combinación al array combinaciones
                combinaciones.push(combinacion);    
            });
            // metemos el array combinaciones dentro del objeto dataObj  
            dataObj['combinaciones'] = combinaciones;             
            console.log(dataObj);
           
            //llamamos por ajax a CrearProductoAtributos
            $.ajax({
            url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=crear_producto_atributos" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            {
                if (typeof data.error === 'undefined')
                {
                    //Si se crea el producto correctamente limpiamos y cerramos el cuadro dialog de atributos
                    $(".combinaciones").remove();
                    $("#span_grupo_atributos").empty();
                    $("#texto_2_atributos").hide();                                            
                    $("#id_attribute_group").remove();
                    $("#span_combinaciones").empty();
                    $("#dialog_atributos").dialog("close"); 

                    //Si se crea el producto correctamente limpiamos también el contenido de las categorías etc del dialog inicial donde se almacena la info del producto copiado                    
                    $("#producto_importar").val('');
                    $('#producto_importar').hide();
                    $("#check_importar").removeAttr('checked'); 
                    $('#categorias_clonar').empty();                    

                    //también quitamos el botón de crear producto de cada línea de atributos, y el checkbox de atributo
                    var id_boton = '';                                        
                    //escondemos los checkbox marcados y los desmarcamos, ya que si creamos otro producto seguido, seguirá marcado                       
                    $('input[type=checkbox]:checked').hide();
                    $('input[type=checkbox]:checked').attr("checked", false);
                    dataObj['combinaciones'].forEach(function cambiaBoton(combinacion){
                        // console.log(combinacion.id_import_catalogos);
                        id_boton = '#botoncito_'+combinacion.id_import_catalogos;
                        $(id_boton).html('<span class="title"><i class="icon-question-sign"></i> Combinación recién creada</span>');                      
                    });                                                            

                    showSuccessMessage(data.message);      
                    
                    //11/08/2021 para evitar dobles clicks y que se creen productos duplicados por error hemos deshabilitado el botón de crear en el dialog de atributos, dado que .off('click') no parece funcionar bien aquí. Lo volvemos a habilitar
                    $('#dialog_submit_combinaciones').removeAttr('disabled');   
                    
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
        });                  

        }

    });

    //cuando se pulsa sobre el botón RESETEAR_PERMITIR_PEDIDO en la página de configuración del módulo, se pide confirmación y se lanza un ajax a la función ResetearPermitir dentro del controlador AdminImportaProveedor
    $('#RESETEAR_PERMITIR_PEDIDO').on('click', function(e){
        //e.preventDefault();
        if (confirm('PRECAUCIÓN ¿Deseas desactivar Permitir Pedidos para todos los productos del catálogo de Prestashop, a excepción de los que tengan la categoría Prepedido?')) {
            //el token para el controlador lo sacamos del value del input hidden "hidden_token" en la página de configuración
            var token_controlador = $('#hidden_token').val();
            
            //llamamos a la función
            $.ajax({
                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token_controlador + "&action=resetear_permitir" + '&ajax=1' + '&rand=' + new Date().getTime(),
                type: 'POST',
                // cache: false,
                // data: datas,                
                // dataType: 'json',
                success: function (data, textStatus, jqXHR)
                {
                    //Si se resetea Permitir Pedido muestra mensaje de éxito                   
                    alert('Reseteado Permitir Pedidos a todos los productos.');
                    showSuccessMessage('Reseteado Permitir Pedidos a todos los productos.');                        
                    
                },
                error: function (jqXHR, textStatus, errorThrown)
                {
                    //showErrorMessage('ERRORS: ' + textStatus);
                    alert("Error"+jqXHR.status);
                }
            });    
        }
        return false;
    });

    //Mostrar mensaje de error si el archivo escogido en la página de configuración es mayor de 2Mb, vaciar input
    $('#csv_importado').on('change', function() { 
        if (this.files[0].size > 2097152) { 
            alert('¡Archivo demasiado grande! El tamaño máximo son 2 megabytes. El tamaño de este archivo son '+this.files[0].size+' bytes. Subir manualmente.'); 
            //limpiamos el input
            $('#csv_importado').val('');
        }        
    }); 

    //mostrar error por tamaño de archivo al pulsar en submit
    // $('#module_form_submit_btn').on('click', function(e){   
    //     //e.stopPropagation();         
    //     var file = $('#csv_importado').files[0];
        
    //     if(file && file.size > 2000000) { //size is in bytes
    //          alert('¡Archivo demasiado grandeeeee!');
    //     } 
    // });

    //29/10/2021 Añadimos funcionalidad para "copiar" las categorías, descripción, seo y tipo de producto de un producto escogido introduciendo una referencia en un input que mostramos en el dialog si se marca el check "Importar características". La descripción, tipo, seo etc no es necesaria aquí, solo mostraremos las categorías y el nombre, y sacaremos los datos completos desde el controlador. Almacenamos en un hidden el id de producto a copiar
    $('#check_importar').on('change', function(e){
        // console.log('check importar caracteristicas tocado');
        //si está checado mostramos el input para referencia, y si existe, el div con las categorías del producto
        if($(this).is(':checked')) {    
            console.log('check importar caracteristicas marcado');
            $('#producto_importar').show();   
            if ($('#categorias_clonar').length) {
                $('#categorias_clonar').show();   
            }                     
            
        }else{
            console.log('check importar caracteristicas desmarcado');
            //si el check se quita, escondemos el input, si se vuelve a marcar, sigue ahí. y si existe, lo mismo para el div con las categorías del producto
            $('#producto_importar').hide();
            if ($('#categorias_clonar').length) {
                $('#categorias_clonar').hide();   
            }  
        }
    });

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

            //Si el formato de la referencia es correcto buscamos si ya existe en la BD, si es así traeremos aquí sus categorías y nombre. La descripción, tipo, seo etc no es necesaria aquí, pero los metemos en input hidden para el controlador al crear el prodcuto.
            $.ajax({
                url: 'index.php?controller=AdminImportaProveedor' + '&token=' + token + "&action=comprobar_referencia_importar" + '&ajax=1' + '&rand=' + new Date().getTime(),
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

                        //mostramos el nombre del producto a "copiar" y sus categorías. Metemos eso en inputs hidden que generamos para procesar desde el controlador.

                        //Creamos una presentación de las categorías del producto, con la principal y el resto. Estas categorías se añadirán al producto a crear
                        //lo ponemos todo debajo del input de referencia del producto a copiar
                        
                        //primero nos aseguramos de eliminar cualquier div clase categorias_clonar por si se hace varias veces para que no se repita
                        $('#categorias_clonar').empty();
                        //construimos lo que queremos mostrar
                        var categorias_secundarias = '';

                        if (!$.isEmptyObject(data.categorias)) {
                            Object.entries(data.categorias).forEach(([key, value]) => {
                                categorias_secundarias +=  '<li>'+value.name+'</li>';
                            });
                        } else {
                            categorias_secundarias = '<li>No tiene</li>';
                        }                        

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
                            <input type='hidden' id='id_producto_copiar' name='id_producto_copiar' value="${data.id_producto_copiar}"></input>
                            <input type='hidden' id='id_category_default' name='id_category_default' value="${data.category_default.id_category}"></input>
                            <input type='hidden' id='ids_categorias' name='ids_categorias' value="${data.ids_categorias}"></input>                                                                            
                        </div>`;

                        var panel_categorias_principal = `
                        <div class="form-group categorias_clonar">
                            <p>
                                ${data.nombre_producto}
                            </p>
                            <label class="control-label col-lg-3">
                                Categoría principal
                            </label>
                            <label class="control-label">
                                <b>${data.category_default.name}</b>
                            </label>                                                                                    
                        </div>`;

                        //hacemos append de lo que queremos mostrar                        
                        $('#categorias_clonar').html(panel_categorias_principal+panel_categorias_secundarias);

                    }
                    else
                    {
                        //Mientras la referencia no sea correcta mostrará el error 
                        
                        showErrorMessage(data.message);
                        //cambiamos el color al fondo del inpit
                        $('#producto_importar').css('background-color', '#e6a5aa');
                        $('#categorias_clonar').empty();
                    }

                },
                error: function (jqXHR, textStatus, errorThrown)
                {
                    showErrorMessage('ERRORS: ' + textStatus);
                    $('#producto_importar').css('background-color', '#e6a5aa');
                    $('#categorias_clonar').empty();
                }
            });          
         
        }else{
            console.log('La referencia NO es correcta');
            showErrorMessage('La referencia NO es correcta');
            $('#producto_importar').css('background-color', '#e6a5aa');
            $('#categorias_clonar').empty();
        }           


    });

});
