<?php

namespace Xenon\LaravelBDSms;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Xenon\LaravelBDSms\Handler\RenderException;
use Xenon\LaravelBDSms\Job\SendSmsJob;

class Request extends Controller
{
    private bool $queue;

    private string $queueName;

    private string $requestUrl;

    private array $query;

    private array $form_params;

    private array $headers;

    private bool $contentTypeJson = false;

    private int $tries;

    private int $backoff;

    /**
     * Constructor For Initiating Value
     */
    public function __construct($requestUrl, array $query, bool $queue = false, array $headers = [], string $queueName = 'default', int $tries = 3, int $backoff = 60)
    {
        $this->requestUrl = $requestUrl;
        $this->query = $query;
        $this->queue = $queue;
        $this->headers = $headers;
        $this->queueName = $queueName;
        $this->tries = $tries;
        $this->backoff = $backoff;
    }

    /**
     * @param  false  $verify
     *
     * @throws GuzzleException
     * @throws RenderException
     */
    public function get(bool $verify = false, float $timeout = 10.0)
    {
        $client = new Client([
            'base_uri' => $this->requestUrl,
            'timeout' => $timeout,
        ]);

        $requestOptions = $this->optionsGetRequest($verify, $timeout);
        if ($this->getQueue()) {
            dispatch(new SendSmsJob($requestOptions))->onQueue($this->queueName);
        } else {

            try {
                return $client->request('get', $this->requestUrl, $requestOptions);
            } catch (GuzzleException|ClientException $e) {
                throw new RenderException($e->getMessage());
            }
        }

    }

    /**
     * @return ResponseInterface
     *
     * @throws RenderException
     */
    public function post(bool $verify = false, float $timeout = 20.0)
    {
        $client = new Client([
            'base_uri' => $this->requestUrl,
            'timeout' => $timeout,
        ]);

        $requestOptions = $this->optionsPostRequest($verify, $timeout);

        try {
            if ($this->getQueue()) {
                dispatch(new SendSmsJob($requestOptions))->onQueue($this->queueName);
            } else {
                return $client->request('post', $this->requestUrl, $requestOptions);
            }

        } catch (GuzzleException|ClientException $e) {
            throw new RenderException($e->getMessage());
        }
    }

    public function getQueue(): bool
    {
        return $this->queue;
    }

    /**
     * @return void
     */
    public function setQueue(bool $queue)
    {
        $this->queue = $queue;
    }

    public function getRequestUrl(): string
    {
        return $this->requestUrl;
    }

    /**
     * @param  mixed  $requestUrl
     */
    public function setRequestUrl($requestUrl): void
    {
        $this->requestUrl = $requestUrl;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param  mixed  $query
     */
    public function setQuery($query): void
    {
        $this->query = $query;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): Request
    {
        $this->headers = $headers;

        return $this;
    }

    public function isContentTypeJson(): bool
    {
        return $this->contentTypeJson;
    }

    public function setContentTypeJson(bool $contentTypeJson): Request
    {
        $this->contentTypeJson = $contentTypeJson;

        return $this;
    }

    public function getFormParams(): array
    {
        return $this->form_params;
    }

    public function setFormParams(array $form_params): Request
    {
        $this->form_params = $form_params;

        return $this;
    }

    /**
     * @param  mixed  $timeout
     */
    private function optionsGetRequest(bool $verify, float $timeout): array
    {
        $options = [
            'requestUrl' => $this->requestUrl,
            'query' => $this->query,
            'verify' => $verify,
            'timeout' => $timeout,
            'method' => 'get',
            'tries' => $this->tries,
            'backoff' => $this->backoff,
        ];
        if (! empty($this->headers)) {
            $options['headers'] = $this->headers;
        }

        if ($this->isContentTypeJson()) {
            unset($options['query']);
            $options[RequestOptions::JSON] = $this->query;
        }

        return $options;
    }

    /**
     * @param  mixed  $timeout
     */
    private function optionsPostRequest(bool $verify, float $timeout): array
    {
        $options = [
            'requestUrl' => $this->requestUrl,
            'query' => $this->query,
            'verify' => $verify,
            'timeout' => $timeout,
            'method' => 'post',
            'tries' => $this->tries,
            'backoff' => $this->backoff,
        ];
        if (! empty($this->headers)) {
            $options['headers'] = $this->headers;
        }

        if (! empty($this->form_params)) {
            $options['form_params'] = $this->form_params;
        }

        if ($this->isContentTypeJson()) {
            $options[RequestOptions::JSON] = $this->query;
        }

        return $options;
    }
}
