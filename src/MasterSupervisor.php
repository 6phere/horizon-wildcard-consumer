<?php

namespace Aloware\HorizonWildcardConsumer;

use Laravel\Horizon\MasterSupervisor as BaseMasterSupervisor;

class MasterSupervisor extends BaseMasterSupervisor
{
    /*
     * Last run of queue observer method
     *
     * @var
     */
    public $lastRun;

    /**
     * Monitor the worker processes.
     *
     * @return void
     */
    public function monitor()
    {
        [$provisioning, $env] = func_get_args();

        $this->ensureNoOtherMasterSupervisors();

        $this->listenForSignals();

        $this->persist();

        while (true) {
            sleep(1);

            if ($provisioning->shouldRun()) {
                $updatedSupervisors = $provisioning->updatedSupervisors($env);
                if (count($updatedSupervisors) > 0) {
                    $supervisors = $this
                        ->supervisors
                        ->filter(
                            function ($supervisor) use ($updatedSupervisors) {
                                return in_array($supervisor->name, $updatedSupervisors, true);
                            }
                        );

                    if ($supervisors->count() > 0) {
                        dump('terminating ' . $supervisors->count() . ' supervisors');
                        $supervisors->each->terminate();
                        $provisioning->deploy($env);
                    }
                }
            }

            $this->loop();
        }
    }
}
