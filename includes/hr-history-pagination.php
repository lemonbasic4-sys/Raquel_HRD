<?php if (!isset($historyPage)) return; ?>
<nav class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-top bg-white rounded-bottom" aria-label="Evaluation history pages">
    <span class="small text-muted">Showing <?php echo (int)$historyPage['first']; ?>–<?php echo (int)$historyPage['last']; ?> of <?php echo (int)$historyPage['total']; ?> results</span>
    <div class="d-flex align-items-center flex-wrap gap-1">
        <?php if ($historyPage['page'] > 1): ?><a class="btn btn-sm btn-outline-success" href="<?php echo e(hrHistoryPageUrl($historyPage['filters'], $historyPage['page']-1)); ?>" rel="prev">Previous</a><?php endif; ?>
        <?php $previousPage = 0; for ($p=1; $p<=$historyPage['pages']; $p++):
            if ($p !== 1 && $p !== $historyPage['pages'] && abs($p-$historyPage['page']) > 2) continue;
            if ($previousPage && $p > $previousPage + 1): ?><span class="px-1 text-muted" aria-hidden="true">…</span><?php endif; ?>
            <a class="btn btn-sm <?php echo $p === $historyPage['page'] ? 'btn-success' : 'btn-outline-success'; ?>" href="<?php echo e(hrHistoryPageUrl($historyPage['filters'], $p)); ?>" <?php echo $p === $historyPage['page'] ? 'aria-current="page"' : ''; ?> aria-label="Page <?php echo $p; ?>"><?php echo $p; ?></a>
        <?php $previousPage=$p; endfor; ?>
        <?php if ($historyPage['page'] < $historyPage['pages']): ?><a class="btn btn-sm btn-outline-success" href="<?php echo e(hrHistoryPageUrl($historyPage['filters'], $historyPage['page']+1)); ?>" rel="next">Next</a><?php endif; ?>
    </div>
</nav>
