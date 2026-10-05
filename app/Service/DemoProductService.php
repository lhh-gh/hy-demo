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

namespace App\Service;

use App\Annotation\Operation;
use App\Constants\DemoErrorCode;
use InvalidArgumentException;
use OutOfBoundsException;

#[Operation('product.read')]
class DemoProductService
{
    private const PRODUCTS = [
        1001 => [
            'id' => 1001,
            'name' => '机械键盘',
            'price_cent' => 19900,
        ],
        1002 => [
            'id' => 1002,
            'name' => '无线鼠标',
            'price_cent' => 9900,
        ],
    ];

    public function detail(int $id): array
    {
        return self::PRODUCTS[$id] ?? throw new OutOfBoundsException(
            DemoErrorCode::getMessage(DemoErrorCode::PRODUCT_NOT_FOUND),
            DemoErrorCode::PRODUCT_NOT_FOUND,
        );
    }

    #[Operation('product.quote')]
    public function quote(int $id, mixed $quantity): array
    {
        $quantity = is_int($quantity) || is_string($quantity)
            ? filter_var($quantity, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 99,
                ],
            ])
            : false;

        if ($quantity === false) {
            throw new InvalidArgumentException(
                DemoErrorCode::getMessage(DemoErrorCode::INVALID_QUANTITY),
                DemoErrorCode::INVALID_QUANTITY,
            );
        }

        $product = self::PRODUCTS[$id] ?? throw new OutOfBoundsException(
            DemoErrorCode::getMessage(DemoErrorCode::PRODUCT_NOT_FOUND),
            DemoErrorCode::PRODUCT_NOT_FOUND,
        );

        return [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'quantity' => $quantity,
            'unit_price_cent' => $product['price_cent'],
            'total_price_cent' => $product['price_cent'] * $quantity,
        ];
    }
}
