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
    <div class="panel-heading">
        <i class="icon-truck"></i>
        Gestión de proveedores
    </div>
    <div class="row form-group">        
        {* Proveedores que están configurados para permitir pedido de forma automática en lafrips_configuration *}
        <div class="col-lg-4">
            <form action="" method="post"> 
                <label class="control-label">Proveedores en tabla con Permitir Automatizado</label> 
                <ul class="list-group list-group-flush">             
                {foreach $proveedores as $proveedor}
                    <li class="list-group-item">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="checkbox_{$proveedor['id_proveedor']}"  value="{$proveedor['id_proveedor']}" name="proveedores_automatizados[]" {if $proveedor['automatizado']} checked {/if}>
                            <label class="custom-control-label" for="checkbox_{$proveedor['id_proveedor']}"> {$proveedor['nombre_proveedor']}</label>
                        </div>
                    </li>
                {/foreach} 
                </ul> 
        </div> 
        <div class="col-lg-2"> 
                <br><br>           
                <label class="control-label" for="submit_proveedores_automatizados">Marca los proveedores para los que quieras automatizar Permitir Pedidos y desmarca los que no.</label>
                <br>                 
                <button type="submit" class="btn btn-success" id="submit_proveedores_automatizados" name="submit_proveedores_automatizados"> 
                <i class="icon icon-save"></i> Guardar
                </button>
            </form>
        </div>               
    </div>
</div>