import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

// Solid red so a sent (locked) invoice stands out in every list and page.
export function SentBadge({ className, children }) {
    return (
        <Badge className={cn('border-transparent bg-red-600 text-white hover:bg-red-600', className)}>
            {children}
        </Badge>
    );
}
