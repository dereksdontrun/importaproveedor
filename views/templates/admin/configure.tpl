{*
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel">
	<h3><i class="icon icon-android"></i> {l s='Importar desde Proveedor' mod='importaproveedor'}</h3>
	<p>
		<strong>{l s='¡Hola holita!' mod='importaproveedor'}</strong><br />
		{l s='Desde aquí puedes decidir si los productos correspondientes a los catálogos de proveedor almacenados deben configurarse como Permitir Pedido cuando su stock disponible llegue a 0.' mod='importaproveedor'}<br />
		{l s='Recuerda que si activas la automatización, la mejor forma de evitar que un producto concreto no pase a Permitir Pedido es añadirle la categoría No Permitir Pedido.' mod='importaproveedor'}
		{l s='Los productos sobre los que se aplicará este automatismo deberán estar activos, en gestión avanzada y disponibles para pedidos con algún proveedor de la tabla de catálogos. No pueden tener atributos o tallas ni ser packs (nativos o del módulo Advanced Packs), y además se excluyen los que estén en las categorías Prepedido o No Permitir Pedido.' mod='importaproveedor'}
	</p>
	<p>
		{l s='Los campos PVP y Antigüedad establecen a partir de que antigüedad del producto, en días, y de que precio de venta, NO se aplicará Permitir Pedido a un producto. P.ej. , PVP = 15 €, Antigüedad = 90 días. No se aplicará Permitir Pedidos a los productos de más de 90 días de antigüedad cuyo PVP sea inferior a 15 €. Al resto de productos que no entren en ese rango si se les aplicará Permitir Pedidos.' mod='importaproveedor'}
	</p>
	<p>
		{l s='Si el campo Antigüedad es 0 y se establece un precio límite, a los productos cuyo PVP sea inferior a ese precio límite NO se les aplicará Permitir Pedido, independientemente de su antigüedad. Si el campo PVP es 0 y se establece una Antigüedad, a los productos con antigüedad superior a la indicada NO se les aplicará Permitir Pedido, independientemente de su PVP. Dejar ambos en 0 para no aplicar ningún filtro.' mod='importaproveedor'}
	</p>
	<br />
	<p>
		{l s='Para otras dudas consulte al oráculo.' mod='importaproveedor'} <i class="icon icon-user-md"></i>
	</p>
	<!-- Pongo un input hidden donde almacenar el token para el controlador AdminImportaProveedor que me va a hacer falta para la llamada Ajax a dicho controlador, que al hacerla desde aquí, tengo un token diferente --> 
	<input type='hidden' id='hidden_token' value='{Tools::getAdminTokenLite("AdminImportaProveedor")}'>
</div>
<!--
<div class="panel">
	<h3><i class="icon icon-tags"></i> {l s='Documentation' mod='prueba_formulario'}</h3>
	<p>
		&raquo; {l s='You can get a PDF documentation to configure this module' mod='prueba_formulario'} :
		<ul>
			<li><a href="#" target="_blank">{l s='English' mod='prueba_formulario'}</a></li>
			<li><a href="#" target="_blank">{l s='French' mod='prueba_formulario'}</a></li>
		</ul>
	</p>
</div>
-->