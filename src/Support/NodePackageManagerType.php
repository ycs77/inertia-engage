<?php

namespace Inertia\Support;

enum NodePackageManagerType: string
{
    case npm = 'npm';

    case yarn = 'yarn';

    case pnpm = 'pnpm';
}
