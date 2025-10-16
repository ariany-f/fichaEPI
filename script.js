$(document).ready(function() {
    // Exibe o loader global
    if ($('#fullpageLoader').length === 0) {
        $('body').append(`
            <div id="fullpageLoader" class="fullpage-loader-bg" style="display:none;">
                <div class="fullpage-loader">
                    <div class="dt-loader"></div>
                    <span>Carregando dados...</span>
                </div>
            </div>
        `);
    }

    // Carrega a lista de colaboradores
    carregarColaboradores();

    // Evento de seleção do colaborador
    $(document).on('change', '#colaboradorSelect', function() {
        const matricula = $(this).val();
        
        if (!matricula) {
            $('#colaboradorInfo').hide();
            $('#dataArea').hide();
            return;
        }
        
        carregarDadosColaborador(matricula);
    });

    // Função para carregar colaboradores
    async function carregarColaboradores() {
        const $select = $('#colaboradorSelect');
        $select.prop('disabled', true);
        $select.html('<option value="">Carregando colaboradores...</option>');
        
        try {
            const response = await fetch('server.php?colaboradores=1');
            
            if (!response.ok) {
                throw new Error('Erro ao carregar colaboradores');
            }
            
            const colaboradores = await response.json();
            
            if (colaboradores.error) {
                throw new Error(colaboradores.error);
            }
            
            $select.html('<option value="">Selecione um colaborador...</option>');
            
            colaboradores.forEach(colab => {
                $select.append(`<option value="${colab.cp_matricula}">${colab.NOMECOLABORADOR} - ${colab.cp_matricula}</option>`);
            });
            
            // Inicializa o Select2 para busca
            $select.select2({
                placeholder: 'Digite para buscar um colaborador...',
                allowClear: true,
                width: '100%'
            });
            
            $select.prop('disabled', false);
            
        } catch (error) {
            console.error('Erro ao carregar colaboradores:', error);
            $select.html('<option value="">Erro ao carregar colaboradores</option>');
            showToast('Erro ao carregar lista de colaboradores!', 'error');
        }
    }

    // Função para carregar dados do colaborador selecionado
    async function carregarDadosColaborador(matricula) {
        $('#fullpageLoader').show();
        
        try {
            const response = await fetch(`server.php?dadosColaborador=1&chapa=${encodeURIComponent(matricula)}`);
            
            if (!response.ok) {
                throw new Error('Erro ao carregar dados do colaborador');
            }
            
            const dados = await response.json();
            
            if (dados.error) {
                throw new Error(dados.error);
            }
            
            if (!dados || dados.length === 0) {
                showToast('Nenhum dado encontrado para este colaborador', 'error');
                $('#colaboradorInfo').hide();
                $('#dataArea').hide();
                return;
            }
            
            // Pega o primeiro registro para exibir informações gerais
            const primeiroRegistro = dados[0];
            
            // Preenche as informações do colaborador
            $('#infoNome').text(primeiroRegistro.NOMECOLABORADOR || '-');
            $('#infoMatricula').text(primeiroRegistro.cp_matricula || '-');
            $('#infoFuncao').text(primeiroRegistro.FUNCAO || '-');
            $('#infoColigada').text(primeiroRegistro.coligada || '-');
            
            $('#colaboradorInfo').slideDown();
            
            // Inicializa a tabela de EPIs
            inicializarTabelaEPIs(dados);
            
            // Adiciona botão para gerar PDF
            adicionarBotaoPDF(primeiroRegistro);
            
            $('#dataArea').slideDown();
            
        } catch (error) {
            console.error('Erro ao carregar dados do colaborador:', error);
            showToast('Erro ao carregar dados do colaborador!', 'error');
            $('#colaboradorInfo').hide();
            $('#dataArea').hide();
        } finally {
            $('#fullpageLoader').fadeOut(200);
        }
    }

    // Função para adicionar botão de gerar PDF
    function adicionarBotaoPDF(dadosColaborador) {
        // Remove botão anterior se existir
        $('#btnGerarPDF').remove();
        
        // Adiciona botão após o título da área de dados
        $('#dataArea h2').after(`
            <div style="margin: 15px 0; text-align: right;">
                <button id="btnGerarPDF" class="action-btn transfer-btn" style="margin: 0;">
                    <i class="fa-solid fa-file-pdf"></i> Gerar PDF de Comprovante
                </button>
            </div>
        `);
        
        // Evento do botão
        $(document).off('click', '#btnGerarPDF');
        $(document).on('click', '#btnGerarPDF', function() {
            gerarPDFComprovante(dadosColaborador);
        });
    }

    // Função para gerar PDF do comprovante
    async function gerarPDFComprovante(dadosColaborador) {
        $('#fullpageLoader').show();
        
        try {
            // Pega todos os registros de EPIs da tabela
            const registrosEPIs = [];
            $('#episTable tbody tr').each(function() {
                const row = $(this);
                const htyId = row.data('hty-id'); // Assumindo que o hty_id está armazenado como data attribute
                
                if (htyId) {
                    registrosEPIs.push({
                        hty_id: htyId,
                        apiUrl: `https://api.umov.me/CenterWeb/api/35689ea77ea561a30989fd792306d7f395366f/activityHistoryHierarchical/${htyId}.xml`
                    });
                }
            });
            
            if (registrosEPIs.length === 0) {
                throw new Error('Nenhum registro de EPI encontrado');
            }
            
            showToast(`Buscando dados da API do uMov para ${registrosEPIs.length} registros...`, 'info');
            
            const response = await fetch(`server.php?gerarPDF=1`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `dadosColaborador=${encodeURIComponent(JSON.stringify(dadosColaborador))}&registrosEPIs=${encodeURIComponent(JSON.stringify(registrosEPIs))}`
            });
            
            if (!response.ok) {
                throw new Error('Erro ao gerar PDF');
            }
            
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.error);
            }
            
            if (result.success) {
                showToast('PDF gerado com sucesso!', 'success');
                
                // Abre o PDF em nova aba
                window.open(result.pdfUrl, '_blank');
            } else {
                throw new Error(result.message || 'Erro ao gerar PDF');
            }
            
        } catch (error) {
            console.error('Erro ao gerar PDF:', error);
            showToast('Erro ao gerar PDF: ' + error.message, 'error');
        } finally {
            $('#fullpageLoader').fadeOut(200);
        }
    }

    // Função para inicializar a tabela de EPIs
    function inicializarTabelaEPIs(dados) {
        // Destroi a tabela se já existir
        if ($.fn.DataTable.isDataTable('#episTable')) {
            $('#episTable').DataTable().destroy();
        }
        
        // Função para ordenar datas no formato brasileiro
        $.fn.dataTable.ext.type.order['date-br-pre'] = function (data) {
            if (!data) return 0;
            // Converte DD/MM/YYYY para YYYY-MM-DD para ordenação
            const parts = data.split('/');
            if (parts.length === 3) {
                return new Date(parts[2], parts[1] - 1, parts[0]).getTime();
            }
            return 0;
        };

        // Inicializa o DataTable
        $('#episTable').DataTable({
            data: dados,
            createdRow: function(row, data, dataIndex) {
                // Adiciona o hty_id como data attribute na linha
                $(row).attr('data-hty-id', data.hty_id);
            },
            columns: [
                { 
                    data: 'ite_description',
                    defaultContent: '-'
                },
                { 
                    data: 'cp_ca',
                    defaultContent: '-'
                },
                { 
                    data: 'cp_quantidade',
                    defaultContent: '-',
                    render: function(data) {
                        return data ? parseFloat(data).toFixed(2) : '-';
                    }
                },
                { 
                    data: 'preco',
                    defaultContent: '-',
                    render: function(data) {
                        if (!data) return '-';
                        return 'R$ ' + parseFloat(data).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    }
                },
                { 
                    data: 'entreguepor',
                    defaultContent: '-'
                },
                { 
                    data: 'cp_data',
                    defaultContent: '-',
                    type: 'date-br',
                    render: function(data) {
                        if (!data) return '-';
                        // Formata a data se vier no formato ISO
                        const date = new Date(data);
                        if (!isNaN(date.getTime())) {
                            return date.toLocaleDateString('pt-BR');
                        }
                        return data;
                    }
                },
                { 
                    data: 'cp_assinatura',
                    defaultContent: '-',
                    render: function(data) {
                        if (data && data.trim() !== '') {
                            return `<button class="action-btn ver-assinatura" data-assinatura="${data}" title="Ver assinatura">
                                        <i class="fa-solid fa-signature"></i> Ver
                                    </button>`;
                        }
                        return '<span style="color: #999;">Sem assinatura</span>';
                    }
                }
            ],
            language: {
                lengthMenu: "Mostrar _MENU_ registros por página",
                zeroRecords: "Nenhum EPI encontrado",
                info: "Mostrando página _PAGE_ de _PAGES_ (_TOTAL_ registros)",
                infoEmpty: "Nenhum registro disponível",
                infoFiltered: "(_TOTAL_ filtrados de _MAX_ registros totais)",
                search: "Pesquisar:",
                paginate: {
                    first: "Primeira",
                    last: "Última",
                    next: "Próxima",
                    previous: "Anterior"
                }
            },
            pageLength: -1, // Mostra todos os registros
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            order: [[5, "asc"]] // Ordena por data de entrega (crescente)
        });
    }

    // Evento para ver assinatura
    $(document).on('click', '.ver-assinatura', function() {
        const assinatura = $(this).data('assinatura');
        
        if ($('#assinaturaModal').length === 0) {
        $('body').append(`
                <div id="assinaturaModal" class="custom-modal-bg" style="display:none;">
                    <div class="custom-modal-box" style="max-width: 600px;">
                        <h3><i class="fa-solid fa-signature"></i> Assinatura do Colaborador</h3>
                        <div id="assinaturaContent" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 6px; margin: 15px 0;">
                            <img src="" alt="Assinatura" style="max-width: 100%; height: auto; border: 1px solid #dee2e6; border-radius: 4px;">
                        </div>
                    <div class="custom-modal-actions">
                            <button id="assinaturaClose" class="cancel">Fechar</button>
                    </div>
                </div>
            </div>
        `);
    }

        $('#assinaturaContent img').attr('src', assinatura);
        $('#assinaturaModal').css('display', 'flex');
        
        $(document).off('click', '#assinaturaClose');
        $(document).on('click', '#assinaturaClose', function() {
            $('#assinaturaModal').fadeOut(180);
        });
    });
});

// Função utilitária para mostrar toast
function showToast(msg, type = 'info') {
    Toastify({
        text: msg,
        duration: 3500,
        gravity: 'top',
        position: 'right',
        close: true,
        style: {
            background: type === 'success' ? '#036e35' : (type === 'error' ? '#e53935' : '#2768ae'),
            color: '#fff',
            fontWeight: 500,
            fontSize: '15px',
            borderRadius: '8px',
            boxShadow: '0 2px 8px #036e3533',
            padding: '10px 18px'
        }
    }).showToast();
}
