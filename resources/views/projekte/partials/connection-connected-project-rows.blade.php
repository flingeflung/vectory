@foreach ($connectedProjects as $p)
    @include('projekte.partials.connection-connected-project-row', [
        'p' => $p,
        'connectionId' => $connections->get($p->id)->connection->id,
        'label' => $connections->get($p->id)->label,
    ])
@endforeach
