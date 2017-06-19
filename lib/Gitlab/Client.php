<?php namespace Gitlab;

use Gitlab\Api\AbstractApi;
use Gitlab\Exception\InvalidArgumentException;
use Gitlab\HttpClient\Builder;
use Gitlab\HttpClient\Plugin\ApiVersion;
use Gitlab\HttpClient\Plugin\History;
use Gitlab\HttpClient\Plugin\Authentication;
use Gitlab\HttpClient\Plugin\GitlabExceptionThrower;
use Http\Client\Common\HttpMethodsClient;
use Http\Client\Common\Plugin\AddHostPlugin;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\Plugin\HistoryPlugin;
use Http\Client\HttpClient;
use Http\Discovery\UriFactoryDiscovery;

/**
 * Simple API wrapper for Gitlab
 *
 * @author Matt Humphrey <matt@m4tt.co>
 *
 * @property-read \Gitlab\Api\Groups $groups
 * @property-read \Gitlab\Api\Issues $issues
 * @property-read \Gitlab\Api\Jobs $jobs
 * @property-read \Gitlab\Api\MergeRequests $merge_requests
 * @property-read \Gitlab\Api\MergeRequests $mr
 * @property-read \Gitlab\Api\Milestones $milestones
 * @property-read \Gitlab\Api\Milestones $ms
 * @property-read \Gitlab\Api\ProjectNamespaces $namespaces
 * @property-read \Gitlab\Api\ProjectNamespaces $ns
 * @property-read \Gitlab\Api\Projects $projects
 * @property-read \Gitlab\Api\Repositories $repositories
 * @property-read \Gitlab\Api\Repositories $repo
 * @property-read \Gitlab\Api\Snippets $snippets
 * @property-read \Gitlab\Api\SystemHooks $hooks
 * @property-read \Gitlab\Api\SystemHooks $system_hooks
 * @property-read \Gitlab\Api\Users $users
 * @property-read \Gitlab\Api\Keys $keys
 * @property-read \Gitlab\Api\Tags $tags
 */
class Client
{
    /**
     * Constant for authentication method. Indicates the default, but deprecated
     * login with username and token in URL.
     */
    const AUTH_URL_TOKEN = 'url_token';

    /**
     * Constant for authentication method. Indicates the new login method with
     * with username and token via HTTP Authentication.
     */
    const AUTH_HTTP_TOKEN = 'http_token';

    /**
     * Constant for authentication method. Indicates the OAuth method with a key
     * obtain using Gitlab's OAuth provider.
     */
    const AUTH_OAUTH_TOKEN = 'oauth_token';

    /**
     * @var History
     */
    private $responseHistory;

    /**
     * @var Builder
     */
    private $httpClientBuilder;

    /**
     * Instantiate a new Gitlab client
     *
     * @param Builder $httpClientBuilder
     */
    public function __construct(Builder $httpClientBuilder = null)
    {
        $this->responseHistory = new History();
        $this->httpClientBuilder = $httpClientBuilder ?: new Builder();

        $this->httpClientBuilder->addPlugin(new GitlabExceptionThrower());
        $this->httpClientBuilder->addPlugin(new HistoryPlugin($this->responseHistory));
        $this->httpClientBuilder->addPlugin(new ApiVersion());
        $this->httpClientBuilder->addPlugin(new HeaderDefaultsPlugin([
            'User-Agent' => 'php-gitlab-api (http://github.com/m4tthumphrey/php-gitlab-api)',
        ]));

        $this->setUrl('https://gitlab.com');
    }

    /**
     * Create a Gitlab\Client using an url.
     *
     * @param string $url
     *
     * @return Client
     */
    public static function create($url)
    {
        $client = new self();
        $client->setUrl($url);

        return $client;
    }

    /**
     * Create a Gitlab\Client using an HttpClient.
     *
     * @param HttpClient $httpClient
     *
     * @return Client
     */
    public static function createWithHttpClient(HttpClient $httpClient)
    {
        $builder = new Builder($httpClient);

        return new self($builder);
    }

    /**
     * @param string $name
     *
     * @return AbstractApi|mixed
     * @throws InvalidArgumentException
     */
    public function api($name)
    {
        switch ($name) {

            case 'deploy_keys':
                $api = new Api\DeployKeys($this);
                break;

            case 'groups':
                $api = new Api\Groups($this);
                break;

            case 'issues':
                $api = new Api\Issues($this);
                break;

            case 'jobs':
                $api = new Api\Jobs($this);
                break;

            case 'mr':
            case 'merge_requests':
                $api = new Api\MergeRequests($this);
                break;

            case 'milestones':
            case 'ms':
                $api = new Api\Milestones($this);
                break;

            case 'namespaces':
            case 'ns':
                $api = new Api\ProjectNamespaces($this);
                break;

            case 'projects':
                $api = new Api\Projects($this);
                break;

            case 'repo':
            case 'repositories':
                $api = new Api\Repositories($this);
                break;

            case 'snippets':
                $api = new Api\Snippets($this);
                break;

            case 'hooks':
            case 'system_hooks':
                $api = new Api\SystemHooks($this);
                break;

            case 'users':
                $api = new Api\Users($this);
                break;

            case 'keys':
                $api = new Api\Keys($this);
                break;

            case 'tags':
                $api = new Api\Tags($this);
                break;

            default:
                throw new InvalidArgumentException('Invalid endpoint: "'.$name.'"');

        }

        return $api;
    }

    /**
     * Authenticate a user for all next requests
     *
     * @param string $token Gitlab private token
     * @param string $authMethod One of the AUTH_* class constants
     * @param string $sudo
     * @return $this
     */
    public function authenticate($token, $authMethod = self::AUTH_URL_TOKEN, $sudo = null)
    {
        $this->httpClientBuilder->removePlugin(Authentication::class);
        $this->httpClientBuilder->addPlugin(new Authentication($authMethod, $token, $sudo));

        return $this;
    }

    /**
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->httpClientBuilder->removePlugin(AddHostPlugin::class);
        $this->httpClientBuilder->addPlugin(new AddHostPlugin(UriFactoryDiscovery::find()->createUri($url)));

        return $this;
    }

    /**
     * @param string $api
     * @return AbstractApi
     */
    public function __get($api)
    {
        return $this->api($api);
    }

    /**
     * @return HttpMethodsClient
     */
    public function getHttpClient()
    {
        return $this->httpClientBuilder->getHttpClient();
    }

    /**
     * @return History
     */
    public function getResponseHistory()
    {
        return $this->responseHistory;
    }
}
