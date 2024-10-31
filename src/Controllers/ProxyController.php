<?php

namespace NAPPS\Controllers;

use Proxy\Proxy;
use GuzzleHttp\Client;
use NAPPS\Contracts\IController;
use Proxy\Filter\RemoveEncodingFilter;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Zend\Diactoros\ServerRequestFactory;

class ProxyController implements IController {

    public function registerRoutes() {
        if ($this->is_proxy_path()) {
            add_filter( 'template_include', [$this, 'proxy_page_template'], 99 );
        }
    }

    private function getCurrentUrl() {
        $protocol = is_ssl() ? 'https://' : 'http://';

        $host = null;
        if(isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = parse_url(home_url(), PHP_URL_HOST);
        }

        if(!$host) {
            return '';
        }
        
        return ($protocol) . $host . $_SERVER['REQUEST_URI'];
    }

    private function is_proxy_path() {
        return strpos($this->getCurrentUrl(), home_url() . "/a/napps") !== false;
    }

    /**
     * Status Page
     *
     * @param string $template The request.
     * @return string Template to render
     */
    public function proxy_page_template( $template ) {

        if (!$this->is_proxy_path()) {
            return $template;
        }
            
        // Create a PSR7 request based on the current browser request.
        $server = $_SERVER;

        // Remove proxy prefix from request path
        if(isset($server['REQUEST_URI'])) {
            $server['REQUEST_URI'] = strstr($server['REQUEST_URI'], "/a/napps");
            $server['REQUEST_URI'] = str_replace("/a/napps", "", $server['REQUEST_URI']);
        }

        $request = ServerRequestFactory::fromGlobals($server);
        $guzzle = new Client();
        $proxy = new Proxy(new GuzzleAdapter($guzzle));
        $proxy->filter(new RemoveEncodingFilter());

        try {

            // Forward the request and get the response.
            $response = $proxy->forward($request)->to('https://master.napps-solutions.com/v1/');
            if(strpos($response->getHeaderLine("Content-Type"), "text/html") !== false) {

                status_header(200);
                $data = $response->getBody();
                include_once NAPPS_PLUGIN_DIR . 'views/proxy-request.php';

            } else {
                $emitter = new \Zend\Diactoros\Response\SapiEmitter();
                $emitter->emit($response);
            }
            
        } catch(\GuzzleHttp\Exception\BadResponseException $e) {
            (new \Zend\Diactoros\Response\SapiEmitter)->emit($e->getResponse());
        }

        die();
    }

}