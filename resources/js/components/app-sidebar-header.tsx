import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { portal_url } = usePage<SharedData>().props;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            {portal_url && (
                <div className="ml-auto">
                    <Button variant="outline" size="sm" asChild>
                        <a href={portal_url} target="_blank" rel="noopener noreferrer">
                            <Globe className="mr-1.5 h-3.5 w-3.5" />
                            Portal Vecinal
                        </a>
                    </Button>
                </div>
            )}
        </header>
    );
}
