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

use App\Dto\Registration\RegisterUserInput;
use App\Service\Registration\RegisterUserService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/demo')]
final class RegistrationDemoController extends AbstractController
{
    #[Inject]
    protected RegisterUserService $registration;

    #[PostMapping(path: 'registration')]
    public function register(): ResponseInterface
    {
        $input = RegisterUserInput::fromArray($this->request->all());
        $user = $this->registration->register($input);

        return $this->response->json([
            'ok' => true,
            'data' => $user->toArray(),
        ])->withStatus(201);
    }
}
