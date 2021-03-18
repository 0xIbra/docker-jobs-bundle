function clickHandler(e) {
    let element = e;
    window.location = element.dataset.href;
}

function showLoader() {
    let overlay = $('#loader-overlay');
    let container = $('#loader-container');

    if (overlay != null && container != null) {
        container.addClass('active');
        overlay.addClass('active');
    }
}

function hideLoader() {
    let overlay = $('#loader-overlay');
    let container = $('#loader-container');
    if (overlay != null && container != null) {
        container.removeClass('active');
        overlay.removeClass('active');
    }
}

function getHumanReadableSize(bytes, si=false, dp=1) {
    const thresh = si ? 1000 : 1024;
    if (Math.abs(bytes) < thresh) {
        return bytes + ' B';
    }
    const units = si
        ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
        : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    let u = -1;
    const r = 10**dp;
    do {
        bytes /= thresh;
        ++u;
    } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);
    return bytes.toFixed(dp) + ' ' + units[u];
}
