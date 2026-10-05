<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;
use Hyperf\Constants\Annotation\Message;

#[Constants]
class DemoErrorCode extends AbstractConstants
{
    #[Message('商品不存在')]
    public const PRODUCT_NOT_FOUND = 40401;

    #[Message('购买数量必须是 1 到 99 的整数')]
    public const INVALID_QUANTITY = 42201;
}
