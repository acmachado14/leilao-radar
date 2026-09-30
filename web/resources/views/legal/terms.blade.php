<div>
    <article class="prose prose-invert mx-auto max-w-2xl py-8">
        <h1 class="text-2xl font-bold">Termos de uso</h1>
        @foreach (\App\Support\LegalCopy::termsParagraphs() as $index => $paragraph)
            <p @class(['mt-4', 'text-slate-400' => $index === 0, 'text-slate-300' => $index > 0])>{{ $paragraph }}</p>
        @endforeach
    </article>
</div>
