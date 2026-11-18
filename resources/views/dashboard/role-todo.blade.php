@php
    $roleTodos = $roleTodos ?? ['items' => [], 'done' => 0, 'total' => 0, 'roleLabel' => '', 'title' => "Today's To-Do", 'subtitle' => ''];
    $todoItems = $roleTodos['items'] ?? [];
    $todoDone = (int) ($roleTodos['done'] ?? 0);
    $todoTotal = (int) ($roleTodos['total'] ?? count($todoItems));
    $todoPercent = $todoTotal > 0 ? (int) round(($todoDone / $todoTotal) * 100) : 0;
@endphp

@if(count($todoItems) > 0)
    <button
        type="button"
        class="role-todo-open"
        data-bs-toggle="modal"
        data-bs-target="#roleTodoModal">
        <span class="role-todo-open__label">
            <i class="fas fa-list-check" aria-hidden="true"></i>
            {{ $roleTodos['title'] ?? "Today's To-Do" }}
        </span>
        <span class="role-todo__count">{{ $todoDone }} of {{ $todoTotal }} done</span>
    </button>

    <div
        class="modal fade"
        id="roleTodoModal"
        tabindex="-1"
        aria-labelledby="role-todo-title"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable role-todo-dialog">
            <div class="modal-content role-todo-modal">
                <div class="modal-header role-todo__head">
                    <div>
                        <h3 class="modal-title" id="role-todo-title">{{ $roleTodos['title'] ?? "Today's To-Do" }}</h3>
                        <p class="role-todo__subtitle">{{ $roleTodos['subtitle'] ?? 'Things you can do in your account' }}</p>
                    </div>
                    <div class="role-todo__head-actions">
                        <span class="role-todo__count">{{ $todoDone }} of {{ $todoTotal }} done</span>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="role-todo__progress" aria-hidden="true">
                        <span style="width: {{ $todoPercent }}%"></span>
                    </div>

                    @if($todoDone === $todoTotal)
                        <p class="role-todo__caught-up">You're all caught up for now. You can still open any item below.</p>
                    @endif

                    <ul class="role-todo__list">
                        @foreach($todoItems as $todo)
                            <li class="{{ !empty($todo['done']) ? 'is-done' : '' }} {{ !empty($todo['urgent']) ? 'is-urgent' : '' }}">
                                <a href="{{ $todo['url'] }}">
                                    <span class="role-todo__check" aria-hidden="true">
                                        @if(!empty($todo['done']))
                                            <i class="fas fa-check"></i>
                                        @else
                                            <i class="{{ $todo['icon'] }}"></i>
                                        @endif
                                    </span>
                                    <span class="role-todo__body">
                                        <strong>{{ $todo['title'] }}</strong>
                                        <small>{{ $todo['hint'] }}</small>
                                    </span>
                                    <span class="role-todo__go">
                                        Open
                                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('roleTodoModal');

            if (!modal) {
                return;
            }

            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            var openTodo = function () {
                if (!window.bootstrap) {
                    return;
                }

                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            };

            if (window.bootstrap) {
                openTodo();
                return;
            }

            window.addEventListener('load', openTodo);
        });
    </script>
@endif
