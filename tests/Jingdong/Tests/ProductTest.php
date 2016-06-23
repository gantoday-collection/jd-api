<?php
namespace Jingdong\Tests;

use Jingdong\Helper\ProductHelper;

$productHelper = new ProductHelper('0AAB224A4BED69377222BA60FA493AE8', '41cbe84513c842edb9ac1916f3092068');

return var_dump($productHelper->search('iPhone'));
