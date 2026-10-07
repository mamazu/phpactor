<?php
declare(strict_types=1);

namespace Phpactor\Extension\PHPUnit\LspCommand;

use Amp\Promise;
use Phpactor\CodeTransform\Adapter\WorseReflection\Refactor\WorseOverrideMethod;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\ApplyWorkspaceEditResult;
use Phpactor\WorseReflection\Reflector;
use InvalidArgumentException;

class GenerateTestMethodCommand implements Command
{
    public const NAME = 'generate_test_methods';

    public function __construct(
        private ClientApi $clientApi,
        private Workspace $workspace,
        private WorseOverrideMethod $overrideMethod,
        private Reflector $reflector,
    ) {
    }

    /**
     * @return Promise<ApplyWorkspaceEditResult>
     */
    public function __invoke(string $uri, string $method): Promise
    {
        $textDocument = $this->workspace->get($uri);
        $source = SourceCode::fromStringAndPath($textDocument->text, $textDocument->uri);

        $classes = $this->reflector->reflectClassesIn($source)->classes();
        if (0 === $classes->count()) {
            throw new InvalidArgumentException(
                'No classes in source file'
            );
        }

        $textEdits = $this->overrideMethod->overrideMethod($source, (string) $classes->first()->name(), $method);

        return $this->clientApi->workspace()->applyEdit(new WorkspaceEdit([
            $uri => TextEditConverter::toLspTextEdits($textEdits, $textDocument->text)
        ]), 'Generated test method "'.$method.'" decoration');
    }
}
