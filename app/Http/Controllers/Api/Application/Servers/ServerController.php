<?php

namespace Pterodactyl\Http\Controllers\Api\Application\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;
use Pterodactyl\Transformers\Api\Application\ServerTransformer;
use Pterodactyl\Http\Requests\Api\Application\Servers\GetServerRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\GetServersRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\ServerWriteRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\StoreServerRequest;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;

class ServerController extends ApplicationApiController
{
    /**
     * @var \Pterodactyl\Services\Servers\ServerCreationService
     */
    private $creationService;

    /**
     * @var \Pterodactyl\Services\Servers\ServerDeletionService
     */
    private $deletionService;

    /**
     * @var \Pterodactyl\Contracts\Repository\ServerRepositoryInterface
     */
    private $repository;

    /**
     * @var \Pterodactyl\Repositories\Wings\DaemonServerRepository
     */
    private DaemonServerRepository $daemon;

    /**
     * ServerController constructor.
     */
    public function __construct(
        ServerCreationService $creationService,
        ServerDeletionService $deletionService,
        ServerRepositoryInterface $repository,
        DaemonServerRepository $daemonServerRepository
    ) {
        parent::__construct();

        $this->creationService = $creationService;
        $this->deletionService = $deletionService;
        $this->repository = $repository;
        $this->daemon = $daemonServerRepository;
    }

    /**
     * Return all of the servers that currently exist on the Panel.
     */
    public function index(GetServersRequest $request): array
    {
        $servers = QueryBuilder::for(Server::query())
            ->allowedFilters(['uuid', 'uuidShort', 'name', 'image', 'external_id'])
            ->allowedSorts(['id', 'uuid'])
            ->paginate($request->query('per_page') ?? 50);

        return $this->fractal->collection($servers)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }

    /**
     * Create a new server on the system.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        $server = $this->creationService->handle($request->validated(), $request->getDeploymentObject());

        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->respond(201);
    }

    /**
     * Show a single server transformed for the application API.
     */
    public function view(GetServerRequest $request): array
    {
        return $this->fractal->item($request->getModel(Server::class))
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }

    /**
     * Wings
     */
    public function wingsInfo(GetServerRequest $request): array {
        return $this->daemon->setServer($request->getModel(Server::class))->getDetails();
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function delete(ServerWriteRequest $request, Server $server, string $force = ''): Response
    {
        $this->deletionService->withForce($force === 'force')->handle($server);

        return $this->returnNoContent();
    }
}
