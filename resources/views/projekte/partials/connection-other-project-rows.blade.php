@foreach ($otherProjects as $p)
    @include('projekte.partials.connection-other-project-row', ['p' => $p])
@endforeach
