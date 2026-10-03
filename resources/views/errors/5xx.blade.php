@extends('errors.layout')

@section('code', (string) ($status ?? '5xx'))
@section('title', 'Algo deu errado')
@section('message', 'Não foi possível concluir esta ação agora. Tente novamente em instantes. Nenhum detalhe interno do servidor é exibido aqui.')
