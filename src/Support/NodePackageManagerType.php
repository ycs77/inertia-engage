<?php

namespace Inertia\Support;

enum NodePackageManagerType: string
{
    case NPM = 'npm';

    case YARN = 'yarn';

    case PNPM = 'pnpm';
}
