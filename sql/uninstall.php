<?php
/**
 * Importador de productos desde catálogo de proveedor
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

$sql = array();

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
