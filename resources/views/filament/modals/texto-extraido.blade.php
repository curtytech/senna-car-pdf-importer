<div class="space-y-4">
    <!-- Estatísticas do texto -->
    <div class="grid grid-cols-{{ isset($metodo) ? '4' : '2' }} gap-4 p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
        <div class="text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalLinhas) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total de Linhas</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalCaracteres) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total de Caracteres</div>
        </div>

        @if(isset($metodo))
        <div class="text-center">
            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                {{ $metodo === 'prinsfrank' ? 'PrinsFrank' : 'Smalot' }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Método de Extração</div>
        </div>
        @endif

        @if(isset($tempoProcessamento))
        <div class="text-center">
            <div class="text-lg font-bold text-green-600 dark:text-green-400">
                {{ number_format($tempoProcessamento, 2) }}s
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Tempo de Processamento</div>
        </div>
        @endif
    </div>

    <!-- Texto extraído -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Conteúdo do PDF</h3>
            <div class="flex gap-2">
                <button
                    type="button"
                    onclick="(function(btn){const ta=document.getElementById('texto-extraido'); ta.select(); ta.setSelectionRange(0, 99999); try{document.execCommand('copy');}catch(e){} const orig=btn.textContent; btn.textContent='Copiado!'; btn.classList.remove('bg-blue-500','hover:bg-blue-600'); btn.classList.add('bg-green-500'); setTimeout(()=>{btn.textContent=orig; btn.classList.remove('bg-green-500'); btn.classList.add('bg-blue-500','hover:bg-blue-600');},2000);})(this)"
                    class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                    Copiar Texto
                </button>
                <button
                    type="button"
                    onclick="(function(btn){const texto=document.getElementById('texto-extraido').value; const blob=new Blob([texto],{type:'text/plain;charset=utf-8'}); const url=window.URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download='texto_extraido_'+new Date().toISOString().slice(0,19).replace(/:/g,'-')+'.txt'; document.body.appendChild(a); a.click(); document.body.removeChild(a); window.URL.revokeObjectURL(url); const orig=btn.textContent; btn.textContent='Baixado!'; btn.classList.remove('bg-green-500','hover:bg-green-600'); btn.classList.add('bg-blue-500'); setTimeout(()=>{btn.textContent=orig; btn.classList.remove('bg-blue-500'); btn.classList.add('bg-green-500','hover:bg-green-600');},2000);})(this)"
                    class="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
                    Baixar TXT
                </button>               
            </div>
        </div>

        <div class="relative">
            <textarea
                id="texto-extraido"
                readonly
                class="w-full h-96 p-4 text-sm font-mono bg-white border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                style="white-space: pre-wrap;">{{ $texto }}</textarea>
        </div>
    </div>

    <!-- Informações adicionais -->
    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
        <p><strong>Dica:</strong> Este é o texto bruto extraído do PDF. Use as funções de processamento para converter em dados estruturados.</p>
        @if(isset($metodo))
        <p><strong>Método:</strong>
            @if($metodo === 'prinsfrank')
            PrinsFrank Parser - Mais rápido e eficiente em memória
            @else
            Smalot Parser - Compatibilidade com PDFs mais antigos
            @endif
        </p>
        @endif
    </div>
</div>

<script>
    function copyToClipboard() {
        const textarea = document.getElementById('texto-extraido');
        textarea.select();
        textarea.setSelectionRange(0, 99999); // Para dispositivos móveis

        try {
            document.execCommand('copy');

            // Feedback visual
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Copiado!';
            button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
            button.classList.add('bg-green-500');

            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-500');
                button.classList.add('bg-blue-500', 'hover:bg-blue-600');
            }, 2000);
        } catch (err) {
            console.error('Erro ao copiar texto: ', err);
        }
    }

    function downloadText() {
        const texto = document.getElementById('texto-extraido').value;
        const blob = new Blob([texto], {
            type: 'text/plain;charset=utf-8'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'texto_extraido_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Feedback visual
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Baixado!';
        button.classList.remove('bg-green-500', 'hover:bg-green-600');
        button.classList.add('bg-blue-500');

        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('bg-blue-500');
            button.classList.add('bg-green-500', 'hover:bg-green-600');
        }, 2000);
    }

    window.limparPaginas = function () {
        const ta = document.getElementById('texto-extraido');
        if (!ta) return;
        ta.value = ta.value.replace(/Página\s+\d+\s+de\s+\d+/gi, '');
        console.log('Numeração de páginas removida');
    };
</script>

<div class="flex gap-2">
    <button
        type="button"
        onclick="window.limparPaginas ? window.limparPaginas() : (function(){ const ta=document.getElementById('texto-extraido'); if(!ta) return; ta.value = ta.value.replace(/Página\\s+\\d+\\s+de\\s+\\d+/gi, ''); console.log('Numeração de páginas removida'); })()"
        class="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
        Atualizar Texto
    </button>
</div>