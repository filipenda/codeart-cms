{{-- The picker dialog: put it once on a page that has `<x-codeart::form.media>` or the editor. Opened by the window event
     `codeart:media-open` ({token, multiple, type}), answers with `codeart:media-selected` ({token, items}). --}}
@can('media.view')
    @once
        <x-codeart::media.browser mode="picker" :locales="[]" accept="" />
    @endonce
@endcan
