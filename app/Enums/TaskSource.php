<?php

namespace App\Enums;

enum TaskSource: string
{
    case WorkflowStep = 'workflow_step';
    case Manual = 'manual';
}
