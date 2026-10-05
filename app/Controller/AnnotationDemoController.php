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

namespace App\Controller;

use App\Service\DemoProductService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

#[Controller(prefix: '/annotation-demo/products')]
class AnnotationDemoController extends AbstractController
{
    #[Inject]
    protected DemoProductService $products;

    #[GetMapping(path: '{id:\d+}')]
    public function detail(string $id): ResponseInterface
    {
        try {
            return $this->response->json([
                'code' => 0,
                'message' => 'success',
                'data' => $this->products->detail((int) $id),
            ]);
        } catch (OutOfBoundsException $exception) {
            return $this->errorResponse($exception, 404);
        }
    }

    #[GetMapping(path: '{id:\d+}/quote')]
    public function quote(string $id): ResponseInterface
    {
        try {
            return $this->response->json([
                'code' => 0,
                'message' => 'success',
                'data' => $this->products->quote(
                    (int) $id,
                    $this->request->query('quantity', '1'),
                ),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception, 422);
        } catch (OutOfBoundsException $exception) {
            return $this->errorResponse($exception, 404);
        }
    }

    private function errorResponse(
        Throwable $exception,
        int $status,
    ): ResponseInterface {
        return $this->response->json([
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'data' => null,
        ])->withStatus($status);
    }
}
