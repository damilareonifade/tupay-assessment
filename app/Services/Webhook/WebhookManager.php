<?php

namespace App\Services\Webhook;

use App\Services\Webhook\Drivers\WebhookInterface;
use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class WebhookManager
{
    /**
     * Unresolved driver arrays
     *
     * @var array
     */
    protected $driverResolvers = [];

    /**
     * Resolved driver array
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * Load driver to the unresolved array
     *
     * @param  string|Closure|null  $driver
     * @return WebhookInterface|void
     */
    public function driver(string $name, $driver = null)
    {
        if (
            ! is_null($driver) &&
            ! is_string($driver) &&
            ! ($driver instanceof Closure)
        ) {
            throw new InvalidArgumentException('A webhook driver can only be resolved through class name or closure');
        }

        if (! is_null($driver)) {
            if (
                is_string($driver) &&
                ! (class_exists($driver) && is_subclass_of($driver, WebhookInterface::class))
            ) {
                throw new Exception(sprintf('Webhook driver must implement [%s] interface', WebhookInterface::class));
            }

            $this->driverResolvers[$name] = $driver;
        } else {
            return $this->getResolvedDriver($name);
        }
    }

    /**
     * Resolve or get an already resolved driver instance
     */
    protected function getResolvedDriver(string $name)
    {
        if (! isset($this->driverResolvers[$name])) {
            throw new WebhookNotFoundException(sprintf('"%s" not found as a webhook driver', $name));
        }

        if (! isset($this->drivers[$name])) {
            if (($resolver = $this->driverResolvers[$name]) instanceof Closure) {
                $driver = $this->resolveDriverFromClosure($resolver);
            } else {
                $driver = $this->resolveDriverFromClass($resolver);
            }

            return $this->drivers[$name] = $driver;
        } else {
            return $this->drivers[$name];
        }
    }

    /**
     * Resolve a driver from closure
     */
    protected function resolveDriverFromClosure(Closure $resolver): WebhookInterface
    {
        if (! ($driver = app()->call($resolver)) instanceof WebhookInterface) {
            throw new Exception(sprintf('Closure resolver must return an instance of %s', WebhookInterface::class));
        }

        return $driver;
    }

    /**
     * Resolve a driver from string
     */
    protected function resolveDriverFromClass(string $resolver): WebhookInterface
    {
        return app()->make($resolver);
    }

    /**
     * Proccess Webhook
     */
    public function processWebhook(string $name, Request $request): Response
    {
        try {
            $webhook = $this->driver($name);
            $raw = $request->getContent();
            $array = $request->toArray();

            if ($webhook->validate($request, $array, $raw)) {
                return $webhook->process($request, $array, $raw);
            } else {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Bad request.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
        } catch (WebhookNotFoundException $th) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            throw $th;

            return response()->json([
                'status' => 'failed',
                'message' => 'Internal Server Error.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
