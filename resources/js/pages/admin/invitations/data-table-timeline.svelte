<script lang="ts">
    import { type Invitation, relative } from './types';

    let { invitation }: { invitation: Invitation } = $props();
</script>

<div class="text-xs text-muted">
    <p>
        Sent {relative(invitation.sentAt)}
        <span class="text-faint">· {invitation.invitedBy}</span>
    </p>
    {#if invitation.status === 'pending'}
        <p class="text-faint">Expires {relative(invitation.expiresAt)}</p>
    {/if}
</div>
