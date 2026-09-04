<?php

namespace App\Enums;

enum TaskSource: string
{
    case WorkflowStep = 'workflow_step';
    case GraphicOrder = 'graphic_order';
    case Manual = 'manual';
}
