<?php
namespace BBS\Core;

abstract class Controller
{
    protected Request $request;
    protected array $middleware = [];

    public function __construct()
    {
        $this->request = new Request();
    }

    public function callAction(string $method, array $params): mixed
    {
        return $this->$method(...$params);
    }

    protected function json($data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function view(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function back(): void
    {
        Response::back();
    }

    protected function validate(Request $request, array $rules): array
    {
        $validator = new Validator($request->all(), $rules);
        if ($validator->fails()) {
            $this->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
            exit;
        }
        return $validator->validated();
    }

    protected function authorize(string $permission): bool
    {
        $user = $this->request->user();
        if (!$user) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }
        return true;
    }

    protected function middleware(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }
}
