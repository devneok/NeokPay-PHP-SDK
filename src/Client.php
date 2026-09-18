<?php

declare(strict_types=1);

namespace Neok\Pay;

use Neok\Pay\Exceptions\TransportException;
use Neok\Pay\Http\ResponseParser;
use Neok\Pay\Resources\Checkouts;
use Neok\Pay\Resources\Payments;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Client
{
    private Checkouts $checkouts;

    private Payments $payments;

    public function __construct(private ClientConfig $config, private ClientInterface $httpClient, private RequestFactoryInterface $requestFactory, private StreamFactoryInterface $streamFactory, private ResponseParser $parser = new ResponseParser)
    {
        $this->checkouts = new Checkouts($this);
        $this->payments = new Payments($this);
    }

    public function checkouts(): Checkouts
    {
        return $this->checkouts;
    }

    public function payments(): Payments
    {
        return $this->payments;
    }

    /**
     * @param  array<string,mixed>|null  $body
     * @param  array<string,string>  $headers
     */
    public function request(string $method, string $path, ?array $body = null, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, rtrim($this->config->baseUrl, '/').'/'.ltrim($path, '/'))->withHeader('Authorization', 'Bearer '.$this->config->apiKey())->withHeader('Accept', 'application/json')->withHeader('User-Agent', $this->config->userAgent());
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        } if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $request = $request->withHeader('Content-Type', 'application/json')->withBody($this->streamFactory->createStream($json));
        } try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException('Unable to reach NEOK Pay.');
        }
    }

    public function parser(): ResponseParser
    {
        return $this->parser;
    }
}
