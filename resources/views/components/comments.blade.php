<div id="commentsList">

    @forelse ($comments as $comment)

        @include('components.comment', [
            'comment' => $comment
        ])

    @empty

        <div
            id="emptyComments"
            class="text-muted text-center py-4"
        >
            Chưa có bình luận nào.
        </div>

    @endforelse

</div>
