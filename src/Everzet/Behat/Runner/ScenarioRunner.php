<?php

namespace Everzet\Behat\Runner;

use Symfony\Component\DependencyInjection\Container;

use Everzet\Gherkin\Element\Scenario\ScenarioElement;
use Everzet\Gherkin\Element\Scenario\BackgroundElement;

class ScenarioRunner extends BaseRunner implements RunnerInterface
{
    protected $scenario;
    protected $definitions;

    protected $backgroundRunner;
    protected $tokens = array();
    protected $skip = false;

    public function __construct(ScenarioElement $scenario, BackgroundElement $background = null,
                                Container $container, RunnerInterface $parent)
    {
        $this->scenario     = $scenario;
        $this->definitions  = $container->getStepsLoaderService();

        if (null !== $background) {
            $this->backgroundRunner = new BackgroundRunner(
                $background
              , $this->definitions
              , $container
              , $this
            );
        }

        foreach ($scenario->getSteps() as $step) {
            $this->addChildRunner(new StepRunner($step, $this->definitions, $container, $this));
        }

        parent::__construct('scenario', $container->getEventDispatcherService(), $parent);
    }

    public function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function getScenariosCount()
    {
        return 1;
    }

    public function getStepsCount()
    {
        $count = parent::getStepsCount();

        if ($this->backgroundRunner) {
            $count += $this->backgroundRunner->getStepsCount();
        }

        return $count;
    }

    public function getScenariosStatusesCount()
    {
        return array($this->getStatus() => 1);
    }

    public function getStepsStatusesCount()
    {
        $statuses = parent::getStepsStatusesCount();

        if ($this->backgroundRunner) {
            foreach ($this->backgroundRunner->getStepsStatusesCount() as $status => $count) {
                if (!isset($statuses[$status])) {
                    $statuses[$status] = 0;
                }

                $statuses[$status] += $count;
            }
        }

        return $statuses;
    }

    public function getDefinitionSnippets()
    {
        $snippets = parent::getDefinitionSnippets();

        if ($this->backgroundRunner) {
            $snippets = array_merge($snippets, $this->backgroundRunner->getDefinitionSnippets());
        }

        return $snippets;
    }

    public function getFailedStepRunners()
    {
        $runners = parent::getFailedStepRunners();

        if ($this->backgroundRunner) {
            $runners = array_merge($runners, $this->backgroundRunner->getFailedStepRunners());
        }

        return $runners;
    }

    public function getPendingStepRunners()
    {
        $runners = parent::getPendingStepRunners();

        if ($this->backgroundRunner) {
            $runners = array_merge($runners, $this->backgroundRunner->getPendingStepRunners());
        }

        return $runners;
    }

    public function getScenario()
    {
        return $this->scenario;
    }

    public function isInOutline()
    {
        return $this->getParentRunner() instanceof ScenarioOutlineRunner;
    }

    protected function doRun()
    {
        if (null !== $this->backgroundRunner) {
            $this->backgroundRunner->run();

            $this->skip = $this->backgroundRunner->isSkipped();
        }

        foreach ($this as $runner) {
            if (null !== $this->tokens && count($this->tokens)) {
                $runner->setTokens($this->tokens);
            }

            if (!$this->skip) {
                if (0 !== $runner->run()) {
                    $this->skip = true;
                }
            } else {
                $runner->skip();
            }
        }
    }
}
